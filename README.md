#start cmd
#php artisan serve

#start with local network server
#php artisan serve --host=192.168.0.182 --port=8000


#This is refresh cmd 
#php artisan l5-swagger:generate
#php artisan route:clear
#php artisan config:clear

#after create api 
php artisan make:migration create_about_us
php artisan migrate
php artisan make:model AboutUs
php artisan make:controller Api/AboutUsController