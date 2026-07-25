<?php
/**
 * Logger to container
 */
$container->set('log', function (\Psr\Container\ContainerInterface $container) {
    $settings = $container->get('settings')['system'];
    $logger_settings = $settings['logger'];
    $logger = new \Monolog\Logger($logger_settings['name']);
    $logger->setTimezone(new \DateTimeZone($settings['timezone']));
    $logger->useMicrosecondTimestamps(false);
    $formatter = new \Monolog\Formatter\LineFormatter($logger_settings['format'].PHP_EOL, $logger_settings['time_format']);
    $formatter->ignoreEmptyContextAndExtra(true);
    // separate log files
    foreach (\Monolog\Level::VALUES as $level_number) {
        $level_name = \Monolog\Level::fromValue($level_number)->getName();
        $stream_handler = new \Monolog\Handler\StreamHandler($logger_settings['log_dir'].DS.strtolower($level_name).'_log', $level_number);
        $stream_handler->setFormatter($formatter);
        $filter_handler = new \Monolog\Handler\FilterHandler($stream_handler, $level_number, $level_number);
        $logger->pushHandler($filter_handler);
    }
    // all log file
    $stream_handler = new \Monolog\Handler\StreamHandler($logger_settings['log_dir'].DS.'all_log', 100);
    $stream_handler->setFormatter($formatter);
    $logger->pushHandler($stream_handler);
    return $logger;
});
?>
