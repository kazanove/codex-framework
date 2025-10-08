<?php
declare(strict_types=1);

namespace CodeX\Http;

class Response
{
    private int $statusCode = 200;
    public string $content = '';
    private array $headers = [];

    public function send(): void
    {
        $this->headerRemove('X-Powered-By');
        $this->headerRemove('Expires');
        $this->headerRemove('Cache-Control');
        $this->headerRemove('Pragma');
        if (!headers_sent()) {
            if (!isset($this->headers['Content-Type'])) {
                $this->header('Content-Type', 'text/html; charset=utf-8');
            }
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function header(string $param, string $value): void
    {
        $this->headers[$param] = $value;
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }
    public function redirect(string $url, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->header('Location', $url);
        die();
    }

    public function getHeaders():array
    {
        return $this->headers;
    }

    public function headerRemove(string $key): void
    {
        header_remove($key);
    }
}
