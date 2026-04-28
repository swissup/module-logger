<?php
if (!class_exists(\Magento\Framework\Component\ComponentRegistrar::class)) {
    return;
}

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Swissup_Logger',
    __DIR__
);
