
### Software requirements
Apache (any version)  
PHP 7.2+  
SOAP

### CRON

Import shipping statuses every hour:
> 1 * * * * php src/Core/cli.php app:import-shipping-statuses
