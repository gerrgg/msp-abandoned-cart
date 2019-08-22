# msp-abandoned-cart
A simply plugin designed to capture the email entered on checkout. After getting the email, we grab some session data woocommerce
setup for us. We create a single_event (1 hour away) that checks whether or not the session is still a session. If it's not a
session, it's either expired or became an order. If it is still a session we create another cron (1 day away) which runs a function
to email the user the cart details.
