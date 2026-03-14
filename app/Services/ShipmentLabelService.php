<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Http\Response;

/**
 * ShipmentLabelService — P1-1: PDF Shipping Label
 *
 * Generates professional 100×150mm shipping labels with:
 * - Barcode SVG from tracking number
 * - Sender / Recipient blocks
 * - Route & service info
 * - Dompdf PDF output (fallback: HTML auto-print)
 *
 * Usage: (new ShipmentLabelService())->generate($shipment)
 * Integration: ShipmentWebController::label() calls this service
 */
class ShipmentLabelService
{
    /**
     * Generate shipping label — returns HTTP Response (PDF or HTML)
     */
    public function generate(Shipment $shipment): Response
    {
        $html = $this->buildHtml($shipment);

        // Try Dompdf first
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper([0, 0, 283.46, 425.20]) // 100mm x 150mm in points
                ->setOption('isRemoteEnabled', false)
                ->setOption('defaultFont', 'Arial');

            return new Response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="label-' . ($shipment->tracking_number ?? $shipment->id) . '.pdf"',
            ]);
        }

        // Fallback: HTML with auto-print
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * Build the label HTML template
     */
    private function buildHtml(Shipment $shipment): string
    {
        $tracking = $shipment->tracking_number ?? 'N/A';
        $barcodeSvg = $this->generateBarcodeSvg($tracking);

        $senderName    = e($shipment->sender_name ?? $shipment->account?->name ?? '—');
        $senderPhone   = e($shipment->sender_phone ?? '—');
        $senderCity    = e($shipment->origin_city ?? $shipment->sender_city ?? '—');
        $senderAddress = e($shipment->sender_address ?? '—');

        $recipientName    = e($shipment->recipient_name ?? $shipment->receiver_name ?? '—');
        $recipientPhone   = e($shipment->recipient_phone ?? $shipment->receiver_phone ?? '—');
        $recipientCity    = e($shipment->destination_city ?? $shipment->recipient_city ?? '—');
        $recipientAddress = e($shipment->recipient_address ?? $shipment->receiver_address ?? '—');

        $carrier     = e($shipment->carrier_name ?? '—');
        $service     = e($shipment->service_type ?? $shipment->shipment_type ?? '—');
        $weight      = $shipment->total_weight ?? $shipment->weight ?? '—';
        $pieces      = $shipment->parcels_count ?? $shipment->pieces ?? 1;
        $cod         = $shipment->cod_amount ?? 0;
        $date        = $shipment->created_at?->format('Y-m-d') ?? date('Y-m-d');
        $reference   = e($shipment->reference ?? $shipment->order_number ?? '—');

        $codSection = '';
        if ($cod > 0) {
            $codSection = '<div style="background:#fff3cd;border:2px solid #ff6b00;padding:6px;text-align:center;margin-top:6px;border-radius:4px"><strong style="color:#ff6b00;font-size:14px">💰 تحصيل عند الاستلام: ' . number_format($cod, 2) . ' ر.س</strong></div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>بطاقة شحن — {$tracking}</title>
<style>
  @page { size: 100mm 150mm; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Tahoma, sans-serif; width: 100mm; min-height: 150mm; padding: 4mm; font-size: 10px; color: #1a1a1a; direction: rtl; }
  .label-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 4px; margin-bottom: 6px; }
  .label-header h1 { font-size: 13px; margin-bottom: 2px; }
  .tracking { font-size: 16px; font-weight: 700; letter-spacing: 1px; font-family: monospace; direction: ltr; }
  .barcode-wrap { text-align: center; margin: 6px 0; }
  .barcode-wrap svg { max-width: 88mm; height: 30mm; }
  .section { border: 1px solid #ccc; border-radius: 4px; padding: 5px 6px; margin-bottom: 5px; }
  .section-title { font-size: 8px; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 3px; border-bottom: 1px dotted #ddd; padding-bottom: 2px; }
  .field { display: flex; justify-content: space-between; margin-bottom: 2px; }
  .field-label { color: #888; font-size: 9px; }
  .field-value { font-weight: 600; font-size: 10px; }
  .recipient-name { font-size: 14px; font-weight: 700; }
  .route-row { display: flex; justify-content: space-between; align-items: center; background: #f5f5f5; padding: 5px 8px; border-radius: 4px; margin-bottom: 5px; font-size: 11px; }
  .route-city { font-weight: 700; font-size: 13px; }
  .route-arrow { font-size: 16px; color: #666; }
  .meta-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px; }
  .meta-item { text-align: center; background: #f8f8f8; padding: 3px; border-radius: 3px; }
  .meta-item .val { font-weight: 700; font-size: 11px; }
  .meta-item .lbl { font-size: 7px; color: #888; }
  .footer { text-align: center; font-size: 7px; color: #aaa; margin-top: 4px; border-top: 1px solid #eee; padding-top: 3px; }
  @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
  <div class="label-header">
    <h1>SHIPPING GATEWAY</h1>
    <div class="tracking">{$tracking}</div>
  </div>

  <div class="barcode-wrap">{$barcodeSvg}</div>

  <div class="route-row">
    <div><div style="font-size:8px;color:#888">من</div><div class="route-city">{$senderCity}</div></div>
    <div class="route-arrow">←</div>
    <div><div style="font-size:8px;color:#888">إلى</div><div class="route-city">{$recipientCity}</div></div>
  </div>

  <div class="section">
    <div class="section-title">المرسل</div>
    <div class="field"><span class="field-value">{$senderName}</span><span class="field-label">{$senderPhone}</span></div>
    <div style="font-size:9px;color:#666">{$senderAddress}</div>
  </div>

  <div class="section" style="border-color:#333;border-width:2px">
    <div class="section-title">المستلم</div>
    <div class="field"><span class="recipient-name">{$recipientName}</span></div>
    <div class="field"><span class="field-value" style="direction:ltr">{$recipientPhone}</span></div>
    <div style="font-size:9px;color:#666;margin-top:2px">{$recipientAddress}</div>
  </div>

  {$codSection}

  <div class="meta-grid">
    <div class="meta-item"><div class="val">{$carrier}</div><div class="lbl">الناقل</div></div>
    <div class="meta-item"><div class="val">{$weight} kg</div><div class="lbl">الوزن</div></div>
    <div class="meta-item"><div class="val">{$pieces}</div><div class="lbl">القطع</div></div>
  </div>

  <div style="margin-top:4px" class="meta-grid">
    <div class="meta-item"><div class="val">{$service}</div><div class="lbl">الخدمة</div></div>
    <div class="meta-item"><div class="val">{$reference}</div><div class="lbl">المرجع</div></div>
    <div class="meta-item"><div class="val">{$date}</div><div class="lbl">التاريخ</div></div>
  </div>

  <div class="footer">Shipping Gateway Platform — {$date} — Ref: {$reference}</div>

  <script>window.onload=function(){window.print()}</script>
</body>
</html>
HTML;
    }

    /**
     * Generate a simple Code128-style barcode as inline SVG
     */
    private function generateBarcodeSvg(string $text): string
    {
        $bars = [];
        $x = 10;
        $chars = str_split($text);

        // Simple encoding: each character → alternating bars/spaces
        foreach ($chars as $i => $char) {
            $code = ord($char);
            $widths = [
                (($code >> 6) & 3) + 1,
                (($code >> 4) & 3) + 1,
                (($code >> 2) & 3) + 1,
                ($code & 3) + 1,
            ];
            foreach ($widths as $j => $w) {
                if ($j % 2 === 0) {
                    $bars[] = '<rect x="' . $x . '" y="5" width="' . $w . '" height="65" fill="#000"/>';
                }
                $x += $w;
            }
        }

        // Stop pattern
        $bars[] = '<rect x="' . $x . '" y="5" width="3" fill="#000" height="65"/>';
        $x += 5;
        $bars[] = '<rect x="' . $x . '" y="5" width="1" fill="#000" height="65"/>';
        $totalWidth = $x + 20;

        $barsStr = implode("\n", $bars);
        $textX = $totalWidth / 2;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$totalWidth} 85" preserveAspectRatio="xMidYMid meet">
{$barsStr}
<text x="{$textX}" y="82" text-anchor="middle" font-family="monospace" font-size="10" fill="#333">{$text}</text>
</svg>
SVG;
    }
}
