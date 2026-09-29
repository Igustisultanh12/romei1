<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Registrasi IMEI - ROMEI</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 30px;
        }
        .header {
            border-b: 2px solid #1e293b;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #0f172a;
        }
        .logo-dot {
            color: #2563eb;
        }
        .title {
            text-align: right;
            font-size: 16px;
            font-weight: bold;
            color: #475569;
            margin-top: -30px;
            text-transform: uppercase;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }
        .grid {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        td {
            padding: 6px 0;
            vertical-align: top;
        }
        .w-30 { width: 30%; }
        .w-70 { width: 70%; }
        .font-mono { font-family: Courier, monospace; font-weight: bold; }
        .badge {
            background-color: #f0fdf4;
            color: #16a34a;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            display: inline-block;
            border: 1px solid #bbf7d0;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="logo">ROMEI<span class="logo-dot">.</span></div>
            <div class="title">Sertifikat Registrasi IMEI</div>
        </div>

        <!-- DETAIL TRANSAKSI -->
        <div class="grid">
            <div class="section-title">Informasi Dokumen</div>
            <table>
                <tr>
                    <td class="w-30">Nomor Registrasi</td>
                    <td class="w-70 font-mono">{{ $registration_number }}</td>
                </tr>
                <tr>
                    <td class="w-30">Nomor Invoice</td>
                    <td class="w-70 font-mono">{{ $invoice_number }}</td>
                </tr>
                <tr>
                    <td class="w-30">Waktu Validasi</td>
                    <td class="w-70">{{ $created_at }} WIB</td>
                </tr>
                <tr>
                    <td class="w-30">Status Jaringan</td>
                    <td class="w-70"><span class="badge">TERSETUJUI / ACTIVE</span></td>
                </tr>
            </table>
        </div>

        <!-- DETAIL PELANGGAN -->
        <div class="grid">
            <div class="section-title">Data Pemilik Akun</div>
            <table>
                <tr>
                    <td class="w-30">Nama Lengkap</td>
                    <td class="w-70" style="font-weight: bold;">{{ $name }}</td>
                </tr>
                <tr>
                    <td class="w-30">Alamat Email</td>
                    <td class="w-70">{{ $email }}</td>
                </tr>
                <tr>
                    <td class="w-30">Nomor WhatsApp</td>
                    <td class="w-70">{{ $whatsapp_number }}</td>
                </tr>
            </table>
        </div>

        <!-- DETAIL PERANGKAT -->
        <div class="grid">
            <div class="section-title">Spesifikasi Teknis Perangkat</div>
            <table>
                <tr>
                    <td class="w-30">Tipe Slot SIM</td>
                    <td class="w-70">{{ $sim_type }}</td>
                </tr>
                <tr>
                    <td class="w-30">Nomor IMEI 1</td>
                    <td class="w-70 font-mono">{{ $imei1 }}</td>
                </tr>
                @if($imei2)
                <tr>
                    <td class="w-30">Nomor IMEI 2</td>
                    <td class="w-70 font-mono">{{ $imei2 }}</td>
                </tr>
                @endif
                <tr>
                    <td class="w-30">Paket Durasi Aktif</td>
                    <td class="w-70">{{ $package_name }} ({{ $duration_days }} Hari)</td>
                </tr>
                <tr>
                    <td class="w-30">Total Biaya Pajak & Layanan</td>
                    <td class="w-70" style="font-weight: bold; color: #0f172a;">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- PERNYATAAN RESMI -->
        <div style="margin-top: 30px; padding: 15px; bg-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">
            <strong>Pernyataan Sistem:</strong> Dokumen ini dikeluarkan secara otomatis melalui sistem enkripsi API ROMEI setelah data pembayaran terverifikasi secara sah melalui gerbang pembayaran DOKU dan sinkronisasi database CEIR pusat berhasil diselesaikan. Dokumen ini sah dan tidak memerlukan tanda tangan basah.
        </div>

        <!-- FOOTER -->
        <div class="footer">
            ROMEI Platform Network • Terintegrasi Gateway Pembayaran Digital DOKU & Database CEIR Pusat
        </div>
    </div>
</body>
</html>