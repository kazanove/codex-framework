<?php
declare(strict_types=1);

namespace CodeX\Http;

class Response
{
    private int $statusCode = 200;
    public string $content = '';
    private array $header = [];

    public function __construct()
    {
        $header = headers_list();
        foreach ($header as $param) {
            [$key, ] = explode(':', $param);
            header_remove(trim($key));
        }
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->header as $name => $value) {
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
        $this->header[$param] = $value;
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode);
        $this->header('Location', $url);
        return $this;
    }
}
