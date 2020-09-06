<?php

if (!function_exists('RMCroute')) {
    /**
     * Runs dynamic routing
     *
     */
    function RMCroute()
    {
		\DynamicRouter\Router::$controller_ending = 'RMC';
        \DynamicRouter\Router::route();
    }
}