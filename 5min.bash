#!/bin/bash

cd /var/www/scrapers/sirva/current/

casperjs activeRequestScraper.js >> /var/log/Sirva/sirvaindex.log
php -f activeRequestParser.php >> /var/log/Sirva/sirvaindex.log

casperjs submittedBidsScraper.js >> /var/log/Sirva/sirvaindex.log
php -f submittedBidsParser.php >> /var/log/Sirva/sirvaindex.log