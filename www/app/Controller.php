<?php

namespace App;

use Slim\Http\Request;
use Slim\Http\Response;


class Controller {

  public function healthcheck(Request $request, Response $response, array $args)
  {
    return $response->withJson(['status' => 'ok', 'php_sapi_name' => php_sapi_name()]);
  }

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

    return $response->withJson($this->utf8ize($data), 200, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  }

  private function utf8ize( $mixed ) {
      if (is_array($mixed)) {
          foreach ($mixed as $key => $value) {
              $mixed[$key] = $this->utf8ize($value);
          }
      } elseif (is_string($mixed)) {
          return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
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

        $data = [
            'status' => 'ok',
            'signedContent' => $this->signedContent
        ];

        try {
//            $CertInfo = new Certificate\Info($cert);
//            $data['cert'] = $this->utf8ize($CertInfo->get());
        } catch (\Exception $e) { }

        return $response->withJson($data);
    }

  public function sign(Request $request, Response $response, array $args)
  {
    $this->getFile($request);
    $this->checkEmptyFile();
    $cert = $this->getCertByQuery($request);

    $signer = new \CPSigner();
    // $signer->set_TSAAddress($address); // Опционально?
    $signer->set_Certificate($cert);
    $pin = $request->getQueryParams()['pin'];

//      $pin = 12345678;
    if(strlen($pin))
    {
      $signer->set_KeyPin($pin);
    }

    $sd = new \CPSignedData;
    $sd->set_ContentEncoding(BASE64_TO_BINARY);
    $sd->set_Content(base64_encode($this->content));

    // Второй параметр - тип подписи(1 = CADES_BES):  http://cpdn.cryptopro.ru/default.asp?url=content/cades/namespace_c_ad_e_s_c_o_m_fe49883d8ff77f7edbeeaf0be3d44c0b_1fe49883d8ff77f7edbeeaf0be3d44c0b.html
    // Третий параметр detached - отделенная(true) или совмещенная (false)
    $detached = $request->getQueryParams()['detached'] == 1;
    $this->signedContent = $sd->SignCades($signer, CADES_BES, $detached, ENCODE_BASE64);

    $data = [
      'status' => 'ok',
      'signedContent' => $this->signedContent
    ];

    try {
      $CertInfo = new Certificate\Info($cert);
      $data['cert'] = $this->utf8ize($CertInfo->get());
    } catch (\Exception $e) { }

    return $response->withJson($data);
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
