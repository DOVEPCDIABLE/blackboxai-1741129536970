class Router {
    private $routes = [];

    public function get($uri, $action) {
        $this->routes['GET'][$uri] = $action;
    }

    public function post($uri, $action) {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch($uri) {
        $method = $_SERVER['REQUEST_METHOD'];
        if (isset($this->routes[$method][$uri])) {
            list($controller, $method) = $this->routes[$method][$uri];
            return (new $controller())->$method();
        }

        // Handle 404
        http_response_code(404);
        return (new ErrorHandler())->handle404();
    }
}
