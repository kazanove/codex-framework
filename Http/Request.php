<?php
declare(strict_types=1);

namespace CodeX\Http;

class Request implements \ArrayAccess
{
    private array $server=[];
    private array $get=[];
    private array $post=[];

    public function __construct()
    {

    }

    public static function createFromGlobals(): Request
    {
        $request = new self();
        $request->server = $_SERVER;
        $request->get = $_GET;
        $request->post = $_POST;
        return $request;
    }

    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD']??'GET';
    }

    public function getPathInfo(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $queryPos = strpos($uri, '?');
        return $queryPos === false ? $uri : substr($uri, 0, $queryPos);
    }

    public function getUri(): string
    {
        return ($this->isHttps()? 'https' : 'http').'://'.$this->host().'/';
    }
    public function isHttps():bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
    public function host():string
    {
        return $_SERVER['HTTP_HOST']??'';
    }
    public function server(): array
    {
        return $_SERVER;
    }
    public function getRequestUri():string
    {
        return $_SERVER['REQUEST_URI']??'';
    }
    public function getQueryString():string
    {
        return $_SERVER['QUERY_STRING']??'';
    }
    public function getQueryParams(): array
    {
        return $_GET;
    }
    public function header(string $key): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$serverKey] ?? null;
    }

    public function offsetExists(mixed $offset): bool
    {
        if($this->getMethod()==='POST'){
            return isset($_POST[$offset]);
        }

        return isset($_GET[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if($this->getMethod()==='POST'){
            return $_POST[$offset];
        }
        return $_GET[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if($this->getMethod()==='POST'){
            $_POST[$offset]=$value;
        }else {
            $_GET[$offset]=$value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if ($this->getMethod() === 'POST') {
             unset($_POST[$offset]);
        } else {
             unset($_GET[$offset]);
        }
    }
}