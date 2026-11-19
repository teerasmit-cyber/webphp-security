FROM php:8.3-fpm-alpine

# ติดตั้ง PDO MySQL สำหรับเชื่อมต่อฐานข้อมูลอย่างปลอดภัย
RUN docker-php-ext-install pdo pdo_mysql

# ตั้งค่าสิทธิ์โฟลเดอร์ uploads ให้ www-data เขียนได้
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads