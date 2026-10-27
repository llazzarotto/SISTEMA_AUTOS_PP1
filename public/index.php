
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(
    dirname(__DIR__) . '/src/Views'
);

$twig = new Environment($loader, [
    'cache' => false,
    'autoescape' => 'html',
    'strict_variables' => false,
]);

echo $twig->render('home.twig', [
    'usuario' => null,
]);
