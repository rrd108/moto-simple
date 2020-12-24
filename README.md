# motopress-hotel-booking simplepay

If you update the hotel booking plugin there is a good chance that you have to do this again manually use `wp-content/plugins/motopress-hotel-booking/includes` as base directory

1. Copy all files from this repo 
2. The only motopress-hotel-booking file what is modified is `wp-content/plugins/motopress-hotel-booking/includes/payments/gateways/gateway-manager.php` at line #35 I added: `new SimplepayGateway();`
3. At the admin site Accomodation > Settings > Payment Gateways > Simplepay all info should filled out
4. Both language Transaction failed (Sikertelen fizetés) page should start with `[simplepay_error]` shortcode
5. Both language Payment success (Sikeres fizetés) page should start with.
