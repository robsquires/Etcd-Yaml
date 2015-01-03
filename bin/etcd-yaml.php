#!/usr/bin/env php
<?php

use Symfony\Component\Yaml\Yaml;
use LinkORB\Component\Etcd\Client;
use LinkORB\Component\Etcd\Exception\KeyNotFoundException;

$loader = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($loader)) {
    $loader = __DIR__ . '/../../../autoload.php';
}

require $loader;


if (!isset($argv[1])) {
    throw new Exception('Specify config file');
}

$cfgPath =  realpath($argv[1]);
if (!$cfgPath) {
    throw new Exception('Config file not found'); 
}

if (!isset($argv[2])) {
    throw new Exception('Specify application dir');
}
$applicationRoot = $argv[2];


$cfg = Yaml::parse(file_get_contents($cfgPath));



$etcd = new Client($cfg['etcd']['host']);

foreach($cfg['mappings'] as $mapping) {

    $paramFile = realpath($applicationRoot . $mapping['file']);
    $originalCfg = Yaml::parse(file_get_contents($paramFile));

    //munge in ectd values
    $overrides = array();
    foreach ($mapping['parameters'] as $node => $path) {

        try {
            $overrides[$node] = $etcd->get($path);
        } catch(KeyNotFoundException $e) {
            //ignore
            continue;
        }
    }

    $mergedCfg = array_merge($originalCfg['parameters'], $overrides);

    file_put_contents(
        $paramFile,
        Yaml::dump(array('parameters' => $mergedCfg), 2, 4)
    );
}
