<?php
declare(strict_types=1);

namespace system;

class Core {
    protected $container;

    public function checkModule(string $module_name) {
        $modules = $this->settings['modules'];

        if (!array_key_exists($module_name, $modules)) {
            throw new \Error("Undefined module: $module_name");
        } elseif (!$modules[$module_name]['enabled']) {
            throw new \Error("Module is disabled: $module_name");
        }
    }

    private function getTory(string $name):array {
        $name = explode('\\', $name);
        if (count($name) === 1) {
            $module = '@'.explode('\\', get_class($this))[2];
        } elseif (count($name) === 2) {
            $module = $name[0];
            $this->checkModule(substr($module, 1));
        }
        $name = end($name);

        return [
            'module' => $module,
            'name' => $name
        ];
    }

    protected function repository(string $name) {
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

    public function __construct($container) {
        $this->container  = $container;
    }

    public function __get($name) {
        if ($this->container->has($name)) {
            return $this->container->get($name); 
        } else {
            throw new \Error("Undefined in container: $name");
        }
    }
}
?>