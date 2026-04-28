# module-logger

### [Installation](https://docs.swissuplabs.com/m2/extensions/logger/installation/)


###### For maintainers

```bash
cd <magento_root>
composer require swissup/module-logger --prefer-source --ignore-platform-reqs
bin/magento module:enable Swissup_Logger Swissup_Core
bin/magento setup:upgrade
bin/magento setup:di:compile
```
