<?php
declare(strict_types=1);

namespace system;

class Core {
    protected $container;
    protected $arguments;

    public function checkModule(string $module_name) {
        $modules = $this->settings['modules'];

        if (!array_key_exists($module_name, $modules)) {
            throw new \Error("Undefined module: $module_name");
        } elseif (!$modules[$module_name]['enabled']) {
            throw new \Error("Module is disabled: $module_name");
        }
    }

    private function getTory(string $name):array {
        $name = explode('/', $name);
        if (count($name) === 1) {
            $module = '@'.explode('\\', get_class($this))[2];
        } elseif (count($name) === 2) {
            $module = '@'.$name[0];
            $this->checkModule(substr($module, 1));
        } else {
            throw new \Error("Invalid repository/factory name: ".implode('/', $name));
        }
        $name = end($name);

        return [
            'module' => $module,
            'name' => $name
        ];
    }

    public function repository(string $name) {
        $r = $this->getTory($name);
        $module = $r['module'];
        $name = $r['name'];
        return $this->{"$module\\repositories\\$name"};
    }

    protected function factory(string $name) {
        $r = $this->getTory($name);
        $module = $r['module'];
        $name = $r['name'];
        return $this->{"$module\\factories\\$name"};
    }

    public function __construct($container, $arguments = null) {
        $this->container  = $container;
        $this->arguments  = $arguments;
    }

    public function __get(string $name) {
        if ($this->container->has($name)) {
            return $this->container->get($name);
        }

        // repository()/factory() build container keys like "@module\repositories\Name";
        // the container only registers one for a class file that actually exists (see
        // init.php), so a miss here always means a missing/mistyped file - point at it
        // directly instead of a bare, easy to misread container key.
        if (preg_match('/^@([^\\\\]+)\\\\(actions|factories|repositories)\\\\(.+)$/', $name, $m)) {
            [, $module, $type, $class] = $m;
            $expectedFile = MODULES_DIR.DS.$module.DS.$type.DS.$class.'.php';
            throw new \Error("Undefined in container: $name (no matching $type class found for the '$module' module - expected file: $expectedFile)");
        }

        throw new \Error("Undefined in container: $name");
    }

    public function __set(string $name, mixed $value) {
        if ($this->container->has($name)) {
            throw new \Error("Exists in container: $name");
        } else {
            $this->container->set($name,$value);
        }
    }
}
?>