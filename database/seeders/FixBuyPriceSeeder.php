<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * One-off correction for GM products whose default_buy_price was entered
 * per Gross (144 pcs) instead of per sale_unit — divides those 82 prices
 * by 12. Only touches default_buy_price; every other column, and every
 * product not in this list, is untouched.
 */
class FixBuyPriceSeeder extends Seeder
{
    public function run(): void
    {
        $fixes = [
            ['product_name' => 'BASKOM 1188', 'default_buy_price' => 25833],
            ['product_name' => 'BASKOM 182', 'default_buy_price' => 34500],
            ['product_name' => 'BASKOM 183', 'default_buy_price' => 42333],
            ['product_name' => 'BASKOM 184', 'default_buy_price' => 59167],
            ['product_name' => 'BASKOM 2288', 'default_buy_price' => 35750],
            ['product_name' => 'BASKOM 28', 'default_buy_price' => 14583],
            ['product_name' => 'BASKOM 38', 'default_buy_price' => 19333],
            ['product_name' => 'BASKOM 68', 'default_buy_price' => 34917],
            ['product_name' => 'BASKOM COR KUPING 27 R HITAM', 'default_buy_price' => 22333],
            ['product_name' => 'BASKOM GERIGI 004', 'default_buy_price' => 14833],
            ['product_name' => 'BASKOM GERIGI 006', 'default_buy_price' => 16500],
            ['product_name' => 'BASKOM GERIGI 008', 'default_buy_price' => 21583],
            ['product_name' => 'BASKOM GERIGI 010', 'default_buy_price' => 30917],
            ['product_name' => 'BASKOM GERIGI 012', 'default_buy_price' => 47500],
            ['product_name' => 'BASKOM GERIGI 014', 'default_buy_price' => 64667],
            ['product_name' => 'BASKOM JERMAN 86', 'default_buy_price' => 17167],
            ['product_name' => 'BASKOM JERMAN 96', 'default_buy_price' => 23750],
            ['product_name' => 'BRUSH SUPER TUTUP SEGI', 'default_buy_price' => 29167],
            ['product_name' => 'CANGKIR 102', 'default_buy_price' => 15583],
            ['product_name' => 'CANGKIR 103', 'default_buy_price' => 18333],
            ['product_name' => 'CANGKIR 168', 'default_buy_price' => 10583],
            ['product_name' => 'CANGKIR ANGGUR 123 8', 'default_buy_price' => 15167],
            ['product_name' => 'CANGKIR TUTUP 12', 'default_buy_price' => 34667],
            ['product_name' => 'CENTONG NASI METALIC', 'default_buy_price' => 17917],
            ['product_name' => 'EMBER SABUN 1 1/2 GL', 'default_buy_price' => 34750],
            ['product_name' => 'EMBER SABUN 1/2', 'default_buy_price' => 21500],
            ['product_name' => 'EMBER SABUN 7/8', 'default_buy_price' => 30917],
            ['product_name' => 'GANTUNGAN BAJU 180', 'default_buy_price' => 42500],
            ['product_name' => 'GANTUNGAN BAJU ANAK 188', 'default_buy_price' => 11000],
            ['product_name' => 'GANTUNGAN BAJU ANGSA 128', 'default_buy_price' => 14917],
            ['product_name' => 'GANTUNGAN BAJU LIDI 168', 'default_buy_price' => 9500],
            ['product_name' => 'GANTUNGAN BAJU PALANG 235', 'default_buy_price' => 31250],
            ['product_name' => 'GAYUNG 747', 'default_buy_price' => 36667],
            ['product_name' => 'GAYUNG 767', 'default_buy_price' => 36667],
            ['product_name' => 'GAYUNG JUMBO 07', 'default_buy_price' => 37500],
            ['product_name' => 'GAYUNG JUMBO 777', 'default_buy_price' => 45667],
            ['product_name' => 'GAYUNG KECIL HITAM', 'default_buy_price' => 17333],
            ['product_name' => 'GAYUNG KECIL WARNA 8', 'default_buy_price' => 16333],
            ['product_name' => 'GAYUNG KILAT 737', 'default_buy_price' => 28417],
            ['product_name' => 'GAYUNG LOVE 757', 'default_buy_price' => 36667],
            ['product_name' => 'GAYUNG MINI 727', 'default_buy_price' => 25417],
            ['product_name' => 'GELAS 122', 'default_buy_price' => 21750],
            ['product_name' => 'GELAS AGOGO', 'default_buy_price' => 17167],
            ['product_name' => 'GELAS AGOGO 8', 'default_buy_price' => 12833],
            ['product_name' => 'GELAS AGOGO 9', 'default_buy_price' => 20833],
            ['product_name' => 'GELAS AGOGO LOVE 800', 'default_buy_price' => 17167],
            ['product_name' => 'GELAS BIR', 'default_buy_price' => 24583],
            ['product_name' => 'GELAS BIR 8', 'default_buy_price' => 18583],
            ['product_name' => 'GELAS CK20', 'default_buy_price' => 22500],
            ['product_name' => 'GELAS DURALEX', 'default_buy_price' => 15667],
            ['product_name' => 'GELAS DURALEX 8', 'default_buy_price' => 11750],
            ['product_name' => 'GELAS MICKEY 888', 'default_buy_price' => 10167],
            ['product_name' => 'KALO BAWANG 230', 'default_buy_price' => 17750],
            ['product_name' => 'KALO BAWANG 235', 'default_buy_price' => 25000],
            ['product_name' => 'KALO OVAL 236 II', 'default_buy_price' => 29667],
            ['product_name' => 'KALO SEGI 338-II', 'default_buy_price' => 28500],
            ['product_name' => 'KALO SEGI 3388', 'default_buy_price' => 36333],
            ['product_name' => 'KALO TELOR BESAR', 'default_buy_price' => 27250],
            ['product_name' => 'KALO TELOR KECIL', 'default_buy_price' => 5083],
            ['product_name' => 'KALO TELOR LAMA', 'default_buy_price' => 14000],
            ['product_name' => 'KOBOKAN 013', 'default_buy_price' => 16417],
            ['product_name' => 'KOBOKAN 03', 'default_buy_price' => 10667],
            ['product_name' => 'MANGKOK 0133', 'default_buy_price' => 15333],
            ['product_name' => 'MANGKOK 133', 'default_buy_price' => 20833],
            ['product_name' => 'MANGKOK 807', 'default_buy_price' => 20833],
            ['product_name' => 'MANGKOK 807 8', 'default_buy_price' => 16583],
            ['product_name' => 'MANGKOK THAILAND', 'default_buy_price' => 25333],
            ['product_name' => 'PIRING 5 AGOGO', 'default_buy_price' => 11500],
            ['product_name' => 'PIRING 6 AGOGO', 'default_buy_price' => 14917],
            ['product_name' => 'PIRING 666', 'default_buy_price' => 9083],
            ['product_name' => 'PIRING 7 AGOGO', 'default_buy_price' => 17000],
            ['product_name' => 'PIRING 7 AGOGO 8', 'default_buy_price' => 13917],
            ['product_name' => 'PIRING 777', 'default_buy_price' => 10167],
            ['product_name' => 'PIRING 8 AGOGO', 'default_buy_price' => 21583],
            ['product_name' => 'PIRING 8 AGOGO 8', 'default_buy_price' => 17500],
            ['product_name' => 'PIRING 9 AGOGO', 'default_buy_price' => 26417],
            ['product_name' => 'PIRING 9 AGOGO 8', 'default_buy_price' => 19167],
            ['product_name' => 'PIRING 999', 'default_buy_price' => 13417],
            ['product_name' => 'PIRING LEXUS SEGI 09', 'default_buy_price' => 27500],
            ['product_name' => 'PIRING LONTONG 09', 'default_buy_price' => 19167],
            ['product_name' => 'SENDOK BEBEK BESAR', 'default_buy_price' => 4500],
            ['product_name' => 'TUTUP & TATAKAN GELAS', 'default_buy_price' => 5333],
        ];

        foreach ($fixes as $fix) {
            Product::updateOrCreate(
                ['product_name' => $fix['product_name']],
                ['default_buy_price' => $fix['default_buy_price']]
            );
        }
    }
}
