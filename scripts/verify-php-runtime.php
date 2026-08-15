<?php

declare(strict_types=1);

/**
 * Enforce FormFlow Lite's distinct PHP host and locked development floors.
 */

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $path = $root . '/' . $relative;
    $contents = is_file($path) ? file_get_contents($path) : false;

    if ($contents === false) {
        $failures[] = sprintf('%s is missing or unreadable', $relative);

        return '';
    }

    return $contents;
};

$composer = json_decode($read('composer.json'), true);
$lock = json_decode($read('composer.lock'), true);

if (!is_array($composer)) {
    $failures[] = 'composer.json is not valid JSON';
} else {
    if (($composer['require']['php'] ?? null) !== '>=8.0') {
        $failures[] = 'composer.json require.php must preserve the PHP 8.0 host floor';
    }
    if (($composer['config']['platform']['php'] ?? null) !== '8.1.0') {
        $failures[] = 'composer.json config.platform.php must be exact PHP 8.1.0';
    }
    if (($composer['require']['peanut/formflow-core'] ?? null) !== '^0.5.0') {
        $failures[] = 'composer.json must retain the shared runtime dependency witness';
    }
}

if (!is_array($lock)) {
    $failures[] = 'composer.lock is not valid JSON';
} else {
    if (($lock['platform']['php'] ?? null) !== '>=8.0') {
        $failures[] = 'composer.lock platform.php must preserve the PHP 8.0 host floor';
    }
    if (($lock['platform-overrides']['php'] ?? null) !== '8.1.0') {
        $failures[] = 'composer.lock platform-overrides.php must be exact PHP 8.1.0';
    }

    $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
    $versions = [];
    foreach ($packages as $package) {
        if (isset($package['name'], $package['version'])) {
            $versions[$package['name']] = $package['version'];
        }
    }
    if (($versions['peanut/formflow-core'] ?? null) !== 'v0.5.0') {
        $failures[] = 'composer.lock must retain peanut/formflow-core v0.5.0';
    }
    if (($versions['doctrine/instantiator'] ?? null) !== '2.0.0') {
        $failures[] = 'composer.lock must retain the PHP 8.1 development-floor witness';
    }
}

if (!preg_match('/^\s*\* Requires PHP:\s*8\.0\s*$/m', $read('formflow-lite.php'))) {
    $failures[] = 'formflow-lite.php must declare PHP 8.0';
}

$readme = $read('README.md');
if (!preg_match('/^- PHP 8\.0\+$/m', $readme)
    || !str_contains($readme, 'complete PHP test suites require')
    || !preg_match('/^- PHP 8\.1\+ for Composer dependencies and test tooling$/m', $readme)) {
    $failures[] = 'README.md must document PHP 8.0 hosts and PHP 8.1 development';
}

$workflow = $read('.github/workflows/tests.yml');
$job = static function (string $name) use ($workflow, &$failures): string {
    $pattern = sprintf('/^  %s:\s*$\R(?<body>(?:(?!^  [a-zA-Z0-9_-]+:\s*$).)*)/ms', preg_quote($name, '/'));
    if (!preg_match($pattern, $workflow, $match)) {
        $failures[] = sprintf('.github/workflows/tests.yml is missing required %s job', $name);

        return '';
    }

    return $match[0];
};

$runtimeJob = $job('php-runtime-minimum');
$developmentJob = $job('php-development-minimum');
$currentJob = $job('php-tests');

$requiredPatterns = [
    [$runtimeJob, '/php-version:\s*["\']8\.0["\']/', 'runtime exact PHP 8.0 setup'],
    [$runtimeJob, '/verify-php-runtime\.php --expect-runtime=8\.0/', 'runtime identity assertion'],
    [$runtimeJob, "/git ls-files -z '\\*\.php' \\| xargs -0 -n1 php -l/", 'tracked-tree parser gate'],
    [$developmentJob, '/name:\s*Net 6 \+ 7 — property & contract \(blocking\)/', 'required unsuffixed context'],
    [$developmentJob, '/php-version:\s*["\']8\.1["\']/', 'development exact PHP 8.1 setup'],
    [$developmentJob, '/verify-php-runtime\.php --expect-development-runtime=8\.1/', 'development identity assertion'],
    [$developmentJob, '/phpunit -c phpunit\.property\.xml/', 'development Property suite'],
    [$developmentJob, '/phpunit -c phpunit\.contract\.xml/', 'development Contract suite'],
    [$developmentJob, '/phpunit -c phpunit\.reliability\.xml/', 'development Reliability suite'],
    [$developmentJob, '/phpunit --testsuite unit/', 'development Unit suite'],
    [$currentJob, '/php-version:\s*\[\s*["\']8\.2["\']\s*,\s*["\']8\.3["\']\s*\]/', 'current PHP 8.2/8.3 matrix'],
];

foreach ($requiredPatterns as [$subject, $pattern, $description]) {
    if (!preg_match($pattern, $subject)) {
        $failures[] = sprintf('%s is missing', $description);
    }
}

if (!preg_match('/php-version:\s*["\']8\.4["\']/', $read('.github/workflows/wp-contract.yml'))) {
    $failures[] = 'wp-contract must retain the real WordPress PHP 8.4 lane';
}

$argument = $argv[1] ?? '';
if ($argument === '--expect-runtime=8.0' && PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION !== '8.0') {
    $failures[] = sprintf('expected the PHP 8.0 host runtime, got %s', PHP_VERSION);
}
if ($argument === '--expect-development-runtime=8.1' && PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION !== '8.1') {
    $failures[] = sprintf('expected the PHP 8.1 development runtime, got %s', PHP_VERSION);
}

if ($failures !== []) {
    fwrite(STDERR, "PHP runtime declaration contract failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "PHP runtime declaration contract passed (host 8.0, development 8.1).\n");
