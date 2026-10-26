<?php

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Авторизация по токену (#48810).
 *
 * Каждый запрос должен прийти с заголовком `Authorization: Bearer <токен>`, где токен —
 * CRYPTOPRO_API_TOKEN из окружения контейнера. Иначе 401. Если токен не задан, сервис
 * отвечает 503 на всё: открытым без токена он не работает.
 */
class TokenAuth implements MiddlewareInterface {

  /**
   * @var string
   */
  private $token;

  public function __construct(string $token)
  {
    $this->token = $token;
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    if ($this->token === '') {
      return $this->fail(503, 'API token is not configured');
    }

    $header = $request->getHeaderLine('Authorization');
    if (strncmp($header, 'Bearer ', 7) !== 0 || !hash_equals($this->token, substr($header, 7))) {
      return $this->fail(401, 'Unauthorized');
    }

    return $handler->handle($request);
  }

  private function fail(int $code, string $message): ResponseInterface
  {
    $response = (new Response($code))->withHeader('Content-Type', 'application/json');
    $response->getBody()->write(json_encode(['status' => 'fail', 'errMsg' => $message, 'errCode' => $code]));

    return $response;
  }
}
