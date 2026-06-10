<?php

namespace App;

use Slim\Psr7\Request;
use Slim\Psr7\Response;


class Controller {

    /**
     * @var mixed
     */
    private $content;

  public function certificates(Request $request, Response $response, array $args)
  {
    $CertFinder = new Certificate\Finder;
    $certificates = $CertFinder->
      fetch()->
      get();
    $data = [
      'status' => 'ok',
      'certificates' => $this->getCertsInfo($certificates)
    ];

    $response->getBody()->write(json_encode($this->utf8ize($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)), 200, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return $response;
  }

  public function license(Request $request, Response $response, array $args)
  {
    $command = '/opt/cprocsp/sbin/amd64/cpconfig -license -view 2>&1';
    exec($command, $output, $returnCode);

    $serial = null;
    $expiresInDays = null;
    $type = null;

    foreach ($output as $i => $line) {
      $line = trim($line);
      if (preg_match('/^Expires:\s*(\d+)\s*day/i', $line, $m)) {
        $expiresInDays = (int) $m[1];
      } elseif (preg_match('/^License type:\s*(.+?)\.?$/i', $line, $m)) {
        $type = trim($m[1]);
      } elseif (preg_match('/^License validity:/i', $line)) {
        // Серийный номер лицензии печатается следующей строкой
        $serial = isset($output[$i + 1]) ? trim($output[$i + 1]) : null;
      }
    }

    $expiresDate = null;
    if ($expiresInDays !== null) {
      $expiresDate = (new \DateTime())->modify("+{$expiresInDays} day")->format('Y-m-d');
    }

    $data = [
      'status' => $returnCode === 0 ? 'ok' : 'error',
      'serial' => $serial,
      'type' => $type,
      'expiresInDays' => $expiresInDays,
      'expiresDate' => $expiresDate,
      'raw' => implode("\n", $output),
    ];

    $response->getBody()->write(json_encode($this->utf8ize($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)), 200, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return $response;
  }

  private function utf8ize( $mixed ) {
      if (is_array($mixed)) {
          foreach ($mixed as $key => $value) {
              $mixed[$key] = $this->utf8ize($value);
          }
      } elseif (is_string($mixed)) {
          return $mixed;
      }
      return $mixed;
  }

  public function unsign(Request $request, Response $response, array $args)
  {
    $this->getFile($request);
    $this->checkEmptyFile();

    $sd = new \CPSignedData;
    $sd->set_ContentEncoding(ENCODE_BINARY);
    $sd->set_Content($this->content);
    // одновременно "расшифровывает"
    $sd->VerifyCades($this->content, CADES_BES, false);

    $data = [
      'status' => 'ok',
      'content' => $sd->get_Content()
    ];

    return $response->withJson($data);
  }

    public function sign2(Request $request, Response $response, array $args)
    {
        $this->getFile($request);
        $this->checkEmptyFile();
        $cert = $this->getCertByQuery($request);

        $pin = $request->getQueryParams()['pin'];

        $detached = $request->getQueryParams()['detached'] == 1;

        $fileName = tempnam('/tmp/', 'mess');
        file_put_contents($fileName, $this->content);

        $sha = $request->getQueryParams()['sha'];

        if($detached){
            $command =            "/opt/cprocsp/bin/amd64/cryptcp -signf -dir /tmp  -cert -thumbprint \"{$sha}\" -nochain --pin {$pin} {$fileName}";
            exec($command);
            $this->signedContent = file_get_contents($fileName.'.sgn');
        }else{
            $command =            "/opt/cprocsp/bin/amd64/cryptcp -sign -dir /tmp  -cert -thumbprint \"{$sha}\" -nochain --pin {$pin} {$fileName}";
            exec($command);
            $this->signedContent = file_get_contents($fileName.'.sig');
        }
//        throw new \Exception();

        exec("/usr/bin/find /tmp -wholename '*mess*' -ctime +1 -delete");

        $this->signedContent = str_replace(["\r", "\n"], '', $this->signedContent);

        $data = [
            'status' => 'ok',
            'signedContent' => $this->signedContent
        ];

        $response->getBody()->write(json_encode($this->utf8ize($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)), 200, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $response;
    }

  public function verify(Request $request, Response $response, array $args)
  {
    $this->getFile($request);
    $this->checkEmptyFile();

    $sd = new \CPSignedData;
    $sd->set_ContentEncoding(BASE64_TO_BINARY);
    $sd->set_Content($this->content);
    // Бросает исключение
    $sd->VerifyCades($this->content, CADES_BES, false);

    $data = [
      'status' => 'ok'
    ];

    $signers = $sd->get_Signers();
    $data['signers'] = $this->getSignersInfo($signers);

    // Возможно получить все сертификаты, в том числе просто приложенные
    // $certificates = $sd->get_Certificates();
    // $data['certificates'] = $this->getCertsInfo($certificates);

    return $response->withJson($data);
  }

  ///////////////////////////////////////// PRIVATE

  private function getFile($request)
  {
    $this->content = $request->getBody()->getContents();
  }

  private function checkEmptyFile()
  {
    if(strlen($this->content) === 0)
    {
      throw new \App\Exception("Empty file", 449);
    }
  }

  private function getCertByQuery(Request $request)
  {
      $sha = $request->getQueryParams()['sha'];
      $CertFinder = new Certificate\Finder;
      $certificates = $CertFinder->
      findType('sha1')
          ->query($sha)->
          fetch()->
          get();
    return $certificates->Item(1);
  }

  private function getCertsInfo(\CPCertificates $certificates)
  {
    $ret = [];
    for($i = 1; $i <= $certificates->Count(); $i++)
    {
      $cert = $certificates->Item($i);
      $CertInfo = new Certificate\Info($cert);
      $ret[] = $CertInfo->get();
    }
    return $ret;
  }

  private function getSignersInfo(\CPSigners $signers)
  {
    $ret = [];
    for($i = 1; $i <= $signers->get_Count(); $i++)
    {
      $cert = $signers->get_Item($i)->get_Certificate();
      $CertInfo = new Certificate\Info($cert);
      $ret[] = $CertInfo->get();
    }
    return $ret;
  }
}
