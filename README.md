Langkah-langkah untuk menjalankan aplikasi, sebagai berikut:
1. Download dan install `xampp`
2. Jalankan atau klik `xampp` yang sudah berhasil di install
3. Masuk ke folder `C:\xampp\htdocs`
4. Clone file `git clone -b master https://github.com/ardi-pras/farmagitech_test.git ./farmagitechs`
5. Buka halaman `phpmyadmin` dari link `http://localhost/phpmyadmin/`
6. Buat database dengan nama `farmagitechs`
7. Import file database `senior_test.sql`
8. Buka postman dan copy link `http://localhost/farmagitechs/public/visit`, isikan `Payload Request Body` sebagai berikut:
    - tipe:harian
    - tgl_awal:2022-01-01
    - tgl_akhir:2022-10-31
    - kategori:Kelurahan
    - kabupaten:10

<!-- ![alt tag](https://github.com/ardi-pras/farmagitech_test/blob/master/payload_response_test_farmagitechs.png) -->