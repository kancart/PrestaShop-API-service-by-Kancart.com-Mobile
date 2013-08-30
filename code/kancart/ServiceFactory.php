<?php

class ServiceFactory {

    private static $serviceCache = array();

    public static function factory($serviceName, $singleton = true) {
        if (empty($serviceName)) {
            throw new Exception('Service name is required .');
        }
        $serviceClassName = $serviceName . 'Service';
        if (isset(self::$serviceCache[$serviceClassName])) {
            if ($singleton) {
                return self::$serviceCache[$serviceClassName];
            } else {
                return new $serviceClassName;
            }
        }
        if (!self::serviceExists($serviceClassName)) {
            throw new Exception($serviceClassName . ' not exists');
        }
        kc_include_once(KANCART_ROOT . '/services/' . $serviceClassName . '.php');
        $instance = new $serviceClassName;
        self::$serviceCache[$serviceClassName] = $instance;
        return $instance;
    }

    public static function serviceExists($serviceClassName) {
        $serviceFile = $serviceClassName . '.php';
        return kc_file_exists(KANCART_ROOT . '/services/' . $serviceFile);
    }

}

?>
