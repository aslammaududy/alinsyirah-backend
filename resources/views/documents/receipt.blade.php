<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bukti Pembayaran {{ $receiptNumber }}</title>
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
        .badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .badge-paid { background-color: #d4edda; color: #155724; }
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

    <div class="doc-title">Bukti Pembayaran</div>

    <div class="info-row">
        <div class="info-block">
            <h3>Info Pembayaran</h3>
            <p><strong>Nomor:</strong> {{ $receiptNumber }}</p>
            <p><strong>Tanggal Bayar:</strong> {{ $attempt->paid_at?->format('d/m/Y H:i') ?? '-' }}</p>
            <p><strong>Status:</strong> <span class="badge badge-paid">LUNAS</span></p>
        </div>
        <div class="info-block">
            <h3>Data Siswa</h3>
            <p><strong>Nama:</strong> {{ $student?->name ?? '-' }}</p>
            <p><strong>NIS:</strong> {{ $student?->nis ?? '-' }}</p>
            <p><strong>Kelas:</strong> {{ $student?->school_class ?? '-' }}</p>
        </div>
        <div class="info-block">
            <h3>Data Pembayaran</h3>
            <p><strong>Order ID:</strong> {{ $attempt->provider_order_id ?? '-' }}</p>
            <p><strong>Status:</strong> {{ ucfirst($attempt->status) }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Invoice</th>
                <th>Periode</th>
                <th>Jenis Biaya</th>
                <th class="amount">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
                <tr>
                    <td>INV-{{ $inv->id }}</td>
                    <td>{{ $inv->period }}</td>
                    <td>{{ ucfirst($inv->fee_type) }}</td>
                    <td class="amount">Rp {{ number_format($inv->pivot->allocated_amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">Total Dibayar</td>
                <td class="amount">Rp {{ number_format($totalAmount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem SIMDIK {{ $school['name'] }}</p>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>
</body>
</html>
