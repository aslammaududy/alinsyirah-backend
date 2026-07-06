<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tagihan {{ $billNumber }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .header img { max-height: 80px; margin-bottom: 10px; }
        .header h1 { margin: 5px 0; font-size: 20px; }
        .header p { margin: 2px 0; font-size: 11px; color: #666; }
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; margin: 20px 0; text-transform: uppercase; }
        .info-row { display: flex; margin-bottom: 15px; }
        .info-block { flex: 1; }
        .info-block h3 { font-size: 12px; margin: 0 0 5px 0; color: #666; text-transform: uppercase; }
        .info-block p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; font-size: 11px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
        .amount { text-align: right; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if($school['logo'])
            <img src="{{ $school['logo'] }}" alt="Logo">
        @endif
        <h1>{{ $school['name'] }}</h1>
        @if($school['address'])
            <p>{{ $school['address'] }}</p>
        @endif
        @if($school['phone'])
            <p>Telp: {{ $school['phone'] }}</p>
        @endif
    </div>

    <div class="doc-title">Tagihan SPP</div>

    <div class="info-row">
        <div class="info-block">
            <h3>Info Tagihan</h3>
            <p><strong>Nomor:</strong> {{ $billNumber }}</p>
            <p><strong>Tanggal:</strong> {{ now()->format('d/m/Y') }}</p>
            <p><strong>Jatuh Tempo:</strong> {{ $invoice->due_date->format('d/m/Y') }}</p>
        </div>
        <div class="info-block">
            <h3>Data Siswa</h3>
            <p><strong>Nama:</strong> {{ $student->name }}</p>
            <p><strong>NIS:</strong> {{ $student->nis }}</p>
            <p><strong>Kelas:</strong> {{ $student->school_class }}</p>
        </div>
        <div class="info-block">
            <h3>Data Orang Tua</h3>
            <p><strong>Nama:</strong> {{ $student->parent_name }}</p>
            <p><strong>HP:</strong> {{ $student->parent_phone }}</p>
            <p><strong>Email:</strong> {{ $student->parent_email }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Periode</th>
                <th>Jenis Biaya</th>
                <th>Deskripsi</th>
                <th class="amount">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->period }}</td>
                <td>{{ ucfirst($invoice->fee_type) }}</td>
                <td>{{ $invoice->description ?? '-' }}</td>
                <td class="amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="3">Total yang Harus Dibayar</td>
                <td class="amount">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem SIMDIK {{ $school['name'] }}</p>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
