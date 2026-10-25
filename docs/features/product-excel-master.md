# Master produk Excel

Form produk, template, ekspor, dan impor mendukung kolom `SKU`, `BARCODE`, `NAMA`, `SATUAN`, `KONVERSI`, `DEPARTEMEN`, `KATEGORI`, `SUB KATEGORI`, `HARGA BELI`, `HARGA JUAL RETAIL`, `LABA(%)`, `RAK`, dan `STOK` seperti `master_toko_coba.xlsx`.

Jalankan `php artisan migrate` setelah memperbarui aplikasi. Migrasi menambahkan metadata master dan mengubah harga produk/satuan menjadi desimal agar harga beli Excel tidak terpotong. Modul transaksi tetap mengikuti aturan pembulatan nominal yang sudah ada.

Di daftar produk, pilih gudang sebelum menekan Import Excel atau Export. Impor mengganti saldo gudang yang dipilih jika STOK diisi, mempertahankan saldo gudang lain, serta mencatat perubahan stok. STOK kosong pada produk baru menjadi nol; pada produk yang sudah ada, saldo dipertahankan. Semua perubahan impor dibatalkan jika ada data yang tidak valid.

Barcode yang sama dianggap sebagai satu produk dengan beberapa satuan. Baris tanpa barcode dicocokkan ke nama produk dalam file; jika barcode dan SKU tetap kosong, aplikasi membuat kode otomatis. SKU dan barcode diekspor sebagai teks agar nol di depan tetap tersimpan. Baris kosong dilewati. Satuan yang sama tidak boleh berulang untuk satu produk.

STOK adalah jumlah dalam SATUAN pada baris tersebut, sedangkan KONVERSI adalah jumlah satuan dasar per satuan itu. Contoh: `KARTON`, konversi `40`, stok `0,45` berarti `18` satuan dasar. Baris eceran dan karton harus menghasilkan saldo dasar yang sama; saldo tidak dijumlahkan. Jika file hanya berisi karton, aplikasi membuat satuan dasar PCS secara otomatis. Jumlah stok dasar harus bulat. Harga dasar dihitung dari harga satuan dibagi konversi.

Ekspor menghasilkan satu baris per satuan. Saldo gudang dikonversi kembali ke masing-masing satuan. Nama yang berbeda antarbaris satuan tetap disimpan. Ekspor dapat diimpor kembali ke gudang yang sama tanpa menggandakan stok. File impor lama dengan kolom `harga_jual`, deskripsi, min/max stok, dan pajak masih diterima.

Gambar, deskripsi, pengaturan stok minimum/maksimum, pajak, komposit, dan satuan tambahan bersifat opsional. Kategori kosong memakai kategori Umum. Field LABA(%) disimpan sebagai metadata dari master, bukan aturan otomatis untuk mengubah harga jual.
