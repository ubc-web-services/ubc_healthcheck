# ubc_healthcheck
Drupal modules that creates a page for monitoring systems to test the status of a site

@author UCB IT WebServices, May 28th 2015
	
This is a basic health check page that is monitored by SCOM.
	
Checks if the database is reachable and we can run a query.
Checks if the default Drupal files folder is reachable and writeable.
	
SCOM checks for the STATUS_OK string to be present in the page.
The STATUS_OK string is included only if all health checks pass.
