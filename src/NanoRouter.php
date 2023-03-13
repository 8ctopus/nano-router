<?php

declare(strict_types=1);

namespace Oct8pus\NanoRouter;

class NanoRouter
{
    private array $routes;
    private array $errors;

    public function __construct()
    {
        $this->routes = [];
        $this->errors = [];
    }

    /**
     * Resolve route
     *
     * @return Response
     */
    public function resolve() : Response
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $routePath => $route) {
            if (!$route['regex']) {
                if ($requestPath === $routePath) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        return $route['callback']();
                    } else {
                        return $this->error(405, $requestPath);
                    }
                }
            } else {
                $matches = null;

                if (preg_match($routePath, $requestPath, $matches) === 1) {
                    if (in_array($route['type'], ['*', $_SERVER['REQUEST_METHOD']], true)) {
                        // call route
                        return $route['callback']($matches);
                    } else {
                        return $this->error(405, $requestPath);
                    }
                }
            }
        }

        return $this->error(404, $requestPath);
    }

    /**
     * Add route
     *
     * @param string   $type
     * @param string   $path
     * @param callable $callback
     *
     * @return self
     */
    public function addRoute(string $type, string $path, callable $callback) : self
    {
        $this->routes[$path] = [
            'type' => $type,
            'callback' => $callback,
            'regex' => false,
        ];

        return $this;
    }

    /**
     * Add regex route
     *
     * @param string   $type
     * @param string   $path
     * @param callable $callback
     *
     * @return self
     *
     * @throws NanoRouterException if regex is invalid
     */
    public function addRouteRegex(string $type, string $path, callable $callback) : self
    {
        // validate regex
        if (!is_int(@preg_match($path, ''))) {
            throw new NanoRouterException('invalid regex');
        }

        $this->routes[$path] = [
            'type' => $type,
            'callback' => $callback,
            'regex' => true,
        ];

        return $this;
    }

    /**
     * Add error handler
     *
     * @param int      $error
     * @param callable $handler
     *
     * @return self
     */
    public function addErrorHandler(int $error, callable $handler) : self
    {
        $this->errors[$error] = [
            'callback' => $handler,
        ];

        return $this;
    }

    /**
     * Deal with error
     *
     * @param int $error
     * @param string $requestPath
     *
     * @return Response
     */
    private function error(int $error, string $requestPath) : Response
    {
        $handler = array_key_exists($error, $this->errors) ? $this->errors[$error] : null;

        if ($handler) {
            // call route
            return $handler['callback']($requestPath);
        } else {
            $messages = [
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Payload Too Large',
                414 => 'URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Range Not Satisfiable',
                417 => 'Expectation Failed',
                418 => 'I\'m a teapot (RFC 2324, RFC 7168)',
                421 => 'Misdirected Request',
                422 => 'Unprocessable Entity',
                423 => 'Locked (WebDAV; RFC 4918)',
                424 => 'Failed Dependency (WebDAV; RFC 4918)',
                425 => 'Too Early (RFC 8470)',
                426 => 'Upgrade Required',
                428 => 'Precondition Required (RFC 6585)',
                429 => 'Too Many Requests (RFC 6585)',
                431 => 'Request Header Fields Too Large (RFC 6585)',
                451 => 'Unavailable For Legal Reasons (RFC 7725)',
            ];

            return new Response($error, $messages[$error]);
        }
    }
}
