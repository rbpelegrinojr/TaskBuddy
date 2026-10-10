<?php
declare(strict_types=1);

namespace TaskBuddy\Support;

/** Tiny pattern router: /api/requests/{id}/invite. Parameters must be numeric unless named {token}. */
final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:array,auth:bool,roles:array}> */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler @param string[] $roles empty = any authenticated role */
    public function add(string $method, string $pattern, array $handler, bool $auth = true, array $roles = []): void
    {
        $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'auth', 'roles');
    }

    /** @return array{route:array,params:array<string,string>}|null */
    public function match(string $method, string $path): ?array
    {
        $pathMatched = false;
        foreach ($this->routes as $r) {
            if (preg_match($r['regex'], $path, $m)) {
                $pathMatched = true;
                if ($r['method'] === $method) {
                    $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                    return ['route' => $r, 'params' => $params];
                }
            }
        }
        if ($pathMatched) {
            throw new ApiException(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.');
        }
        return null;
    }
}
