<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePwaIcons extends Command
{
    protected $signature = 'pwa:icons';
    protected $description = 'Generate PWA icons for Shipping Gateway';

    private array $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

    public function handle(): int
    {
        $outputDir = public_path('icons');
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // ── إذا كان GD متاحاً، ننشئ أيقونات PNG حقيقية ──
        if (extension_loaded('gd')) {
            return $this->generateWithGD($outputDir);
        }

        // ── وإلا ننشئ أيقونات SVG كبديل ──
        return $this->generateSvgFallback($outputDir);
    }

    private function generateWithGD(string $outputDir): int
    {
        foreach ($this->sizes as $size) {
            $img = imagecreatetruecolor($size, $size);
            imagesavealpha($img, true);

            // الخلفية: تدرج من الأزرق إلى البنفسجي
            $blue   = imagecolorallocate($img, 59, 130, 246);  // #3B82F6
            $purple = imagecolorallocate($img, 139, 92, 246);  // #8B5CF6
            $white  = imagecolorallocate($img, 255, 255, 255);

            // رسم الخلفية المتدرجة
            for ($y = 0; $y < $size; $y++) {
                $ratio = $y / max($size - 1, 1);
                $r = (int)(59  + $ratio * (139 - 59));
                $g = (int)(130 + $ratio * (92  - 130));
                $b = (int)(246 + $ratio * (246 - 246));
                $lineColor = imagecolorallocate($img, $r, $g, $b);
                imageline($img, 0, $y, $size - 1, $y, $lineColor);
            }

            // الزوايا الدائرية (للأيقونات الكبيرة)
            if ($size >= 128) {
                $radius = (int)($size * 0.18);
                $this->roundCorners($img, $size, $radius);
            }

            // كتابة "SG" في المنتصف
            $fontSize = (int)($size * 0.35);
            $fontFile = null;

            // محاولة استخدام خط النظام
            $systemFonts = [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/System/Library/Fonts/Helvetica.ttc',
            ];
            foreach ($systemFonts as $font) {
                if (file_exists($font)) {
                    $fontFile = $font;
                    break;
                }
            }

            if ($fontFile) {
                $bbox = imagettfbbox($fontSize, 0, $fontFile, 'SG');
                $textWidth  = $bbox[2] - $bbox[0];
                $textHeight = $bbox[1] - $bbox[7];
                $x = (int)(($size - $textWidth) / 2);
                $y = (int)(($size + $textHeight) / 2);
                imagettftext($img, $fontSize, 0, $x, $y, $white, $fontFile, 'SG');
            } else {
                // fallback: الخط المدمج
                $fontScale = max(1, (int)($size / 40));
                $textWidth  = imagefontwidth($fontScale) * 2;
                $textHeight = imagefontheight($fontScale);
                $x = (int)(($size - $textWidth) / 2);
                $y = (int)(($size - $textHeight) / 2);
                imagestring($img, $fontScale, $x, $y, 'SG', $white);
            }

            // حفظ الأيقونة العادية
            $path = $outputDir . "/icon-{$size}x{$size}.png";
            imagepng($img, $path);
            $this->info("✅ Created: icon-{$size}x{$size}.png");

            // أيقونة maskable (مع padding إضافي)
            if (in_array($size, [192, 512])) {
                $maskable = imagecreatetruecolor($size, $size);
                imagesavealpha($maskable, true);

                // خلفية كاملة بدون زوايا دائرية
                for ($y2 = 0; $y2 < $size; $y2++) {
                    $ratio = $y2 / max($size - 1, 1);
                    $r = (int)(59  + $ratio * (139 - 59));
                    $g = (int)(130 + $ratio * (92  - 130));
                    $b = (int)(246 + $ratio * (246 - 246));
                    $lineColor = imagecolorallocate($maskable, $r, $g, $b);
                    imageline($maskable, 0, $y2, $size - 1, $y2, $lineColor);
                }

                // نفس النص لكن أصغر قليلاً (safe zone 80%)
                $maskFontSize = (int)($size * 0.28);
                if ($fontFile) {
                    $bbox = imagettfbbox($maskFontSize, 0, $fontFile, 'SG');
                    $textWidth  = $bbox[2] - $bbox[0];
                    $textHeight = $bbox[1] - $bbox[7];
                    $x = (int)(($size - $textWidth) / 2);
                    $y2 = (int)(($size + $textHeight) / 2);
                    $white2 = imagecolorallocate($maskable, 255, 255, 255);
                    imagettftext($maskable, $maskFontSize, 0, $x, $y2, $white2, $fontFile, 'SG');
                }

                $maskPath = $outputDir . "/icon-maskable-{$size}x{$size}.png";
                imagepng($maskable, $maskPath);
                imagedestroy($maskable);
                $this->info("✅ Created: icon-maskable-{$size}x{$size}.png");
            }

            imagedestroy($img);
        }

        $this->newLine();
        $this->info('🎉 All PWA icons generated in: public/icons/');
        return self::SUCCESS;
    }

    private function roundCorners(\GdImage &$img, int $size, int $radius): void
    {
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);

        // أربع زوايا
        for ($x = 0; $x < $radius; $x++) {
            for ($y = 0; $y < $radius; $y++) {
                $dist = sqrt(($radius - $x) ** 2 + ($radius - $y) ** 2);
                if ($dist > $radius) {
                    // أعلى يسار
                    imagesetpixel($img, $x, $y, $transparent);
                    // أعلى يمين
                    imagesetpixel($img, $size - 1 - $x, $y, $transparent);
                    // أسفل يسار
                    imagesetpixel($img, $x, $size - 1 - $y, $transparent);
                    // أسفل يمين
                    imagesetpixel($img, $size - 1 - $x, $size - 1 - $y, $transparent);
                }
            }
        }
    }

    private function generateSvgFallback(string $outputDir): int
    {
        $this->warn('⚠ GD extension not available — generating SVG placeholder icons.');
        $this->warn('  Install GD (php-gd) for proper PNG icons.');

        foreach ($this->sizes as $size) {
            $fontSize = (int)($size * 0.35);
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#3B82F6"/>
      <stop offset="100%" style="stop-color:#8B5CF6"/>
    </linearGradient>
  </defs>
  <rect width="{$size}" height="{$size}" rx="{$this->round($size * 0.18)}" fill="url(#bg)"/>
  <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle"
        fill="white" font-family="Arial, sans-serif" font-weight="bold" font-size="{$fontSize}">SG</text>
</svg>
SVG;
            file_put_contents($outputDir . "/icon-{$size}x{$size}.svg", $svg);
            $this->info("✅ Created: icon-{$size}x{$size}.svg");
        }

        $this->newLine();
        $this->info('📝 SVG icons created. Convert to PNG using an image tool for best results.');
        return self::SUCCESS;
    }

    private function round(float $val): int
    {
        return (int) round($val);
    }
}
