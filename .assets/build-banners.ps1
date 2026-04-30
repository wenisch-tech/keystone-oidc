Add-Type -AssemblyName System.Drawing

$ErrorActionPreference = 'Stop'

$assetsDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$iconPath  = Join-Path $assetsDir 'icon-256x256.png'
$shotPath  = Join-Path $assetsDir 'screenshot-5.png'
$outLarge  = Join-Path $assetsDir 'banner-1544x500.png'
$outSmall  = Join-Path $assetsDir 'banner-772x250.png'

# ── Helpers ───────────────────────────────────────────────────────────────────

function New-RoundedPath([float]$X, [float]$Y, [float]$W, [float]$H, [float]$R) {
    $R = [Math]::Min($R, [Math]::Min($W, $H) / 2)
    $d = $R * 2
    $p = New-Object System.Drawing.Drawing2D.GraphicsPath
    $p.AddArc($X,           $Y,           $d, $d, 180, 90)
    $p.AddArc($X + $W - $d, $Y,           $d, $d, 270, 90)
    $p.AddArc($X + $W - $d, $Y + $H - $d, $d, $d,   0, 90)
    $p.AddArc($X,           $Y + $H - $d, $d, $d,  90, 90)
    $p.CloseFigure()
    return $p
}

function Draw-RadialGlow($G, [float]$CX, [float]$CY, [float]$R, $Inner, $Outer) {
    $rect = New-Object System.Drawing.RectangleF(($CX - $R), ($CY - $R), ($R * 2), ($R * 2))
    $path = New-Object System.Drawing.Drawing2D.GraphicsPath
    $path.AddEllipse($rect)
    $b = New-Object System.Drawing.Drawing2D.PathGradientBrush($path)
    $b.CenterColor   = $Inner
    $b.SurroundColors = @($Outer)
    $G.FillEllipse($b, $rect)
    $b.Dispose(); $path.Dispose()
}

# Draw a feature chip: rounded pill with green check icon + label
function Draw-Chip($G, [float]$X, [float]$Y, [float]$W, [float]$H, [string]$Text, $Font) {
    # Pill background
    $path = New-RoundedPath -X $X -Y $Y -W $W -H $H -R ($H / 2)
    $bg   = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(95, 10, 38, 92))
    $pen  = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(145, 90, 170, 255), 1.2)
    $G.FillPath($bg, $path)
    $G.DrawPath($pen, $path)

    # Green check circle
    $ir  = ($H - 18) / 2
    $icx = $X + 14 + $ir
    $icy = $Y + $H / 2
    $gb  = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255, 34, 188, 120))
    $G.FillEllipse($gb, ($icx - $ir), ($icy - $ir), ($ir * 2), ($ir * 2))

    # Checkmark lines
    $cp = New-Object System.Drawing.Pen([System.Drawing.Color]::White, 1.8)
    $cp.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $cp.EndCap   = [System.Drawing.Drawing2D.LineCap]::Round
    $t  = $ir * 0.36
    $G.DrawLine($cp, ($icx - $t), $icy,        $icx,             ($icy + $t))
    $G.DrawLine($cp,  $icx,       ($icy + $t), ($icx + $t * 1.5), ($icy - $t * 1.2))

    # Label text – vertically centred inside the chip
    $tb    = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(242, 232, 248, 255))
    $tx    = $X + 14 + $ir * 2 + 10
    $rectW = ($X + $W) - $tx - 10
    $sf    = New-Object System.Drawing.StringFormat
    $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
    $G.DrawString($Text, $Font, $tb, (New-Object System.Drawing.RectangleF($tx, $Y, $rectW, $H)), $sf)

    $sf.Dispose(); $tb.Dispose(); $cp.Dispose(); $gb.Dispose()
    $pen.Dispose(); $bg.Dispose(); $path.Dispose()
}

# Draw the plugin icon clipped to a circle
function Draw-IconCircle($G, $Icon, [float]$X, [float]$Y, [float]$D) {
    $epath = New-Object System.Drawing.Drawing2D.GraphicsPath
    $epath.AddEllipse($X, $Y, $D, $D)
    $wb = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
    $G.FillEllipse($wb, $X, $Y, $D, $D)
    $wb.Dispose()
    $G.SetClip($epath)
    $G.DrawImage($Icon, $X, $Y, $D, $D)
    $G.ResetClip()
    $epath.Dispose()
}

# Draw the screenshot clipped into a rounded card with shadow
function Draw-ScreenshotCard($G, $Shot, [float]$CX, [float]$CY, [float]$CW, [float]$CH, [float]$R) {
    # Drop shadow
    $shPath  = New-RoundedPath -X ($CX + 9) -Y ($CY + 12) -W $CW -H $CH -R $R
    $shBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(115, 0, 0, 0))
    $G.FillPath($shBrush, $shPath)
    $shBrush.Dispose(); $shPath.Dispose()

    # Clip and fill screenshot
    $cPath = New-RoundedPath -X $CX -Y $CY -W $CW -H $CH -R $R
    $G.SetClip($cPath)

    $scX = $CW / [double]$Shot.Width
    $scY = $CH / [double]$Shot.Height
    $sc  = [Math]::Max($scX, $scY)
    $dW  = [int]($Shot.Width  * $sc)
    $dH  = [int]($Shot.Height * $sc)
    $dX  = $CX + [int](($CW - $dW) / 2)
    $dY  = $CY + [int](($CH - $dH) / 2)
    $G.DrawImage($Shot, $dX, $dY, $dW, $dH)

    # Very subtle tint to blend screenshot with dark banner
    $ov = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(20, 0, 15, 55))
    $G.FillRectangle($ov, $CX, $CY, $CW, $CH)
    $ov.Dispose()

    $G.ResetClip()

    # Card border
    $cPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(175, 175, 210, 255), 1.5)
    $G.DrawPath($cPen, $cPath)
    $cPen.Dispose(); $cPath.Dispose()
}

# ── Load images ───────────────────────────────────────────────────────────────
$icon = [System.Drawing.Image]::FromFile($iconPath)
$shot = [System.Drawing.Image]::FromFile($shotPath)

# ── LARGE BANNER  1544 × 500 ─────────────────────────────────────────────────
$BW = 1544; $BH = 500
$bmp = New-Object System.Drawing.Bitmap($BW, $BH)
$g   = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
$g.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$g.TextRenderingHint  = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

# ── Background ──
$bgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    (New-Object System.Drawing.Rectangle(0, 0, $BW, $BH)),
    [System.Drawing.Color]::FromArgb(255,  4, 12, 34),
    [System.Drawing.Color]::FromArgb(255,  8, 44, 94),
    [System.Drawing.Drawing2D.LinearGradientMode]::Horizontal)
$g.FillRectangle($bgBrush, 0, 0, $BW, $BH)
$bgBrush.Dispose()

# Subtle diagonal grid lines
$linePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(15, 255, 255, 255), 1)
for ($i = -500; $i -lt 1850; $i += 44) { $g.DrawLine($linePen, $i, 0, $i + 310, $BH) }
$linePen.Dispose()

# Radial glow accents
Draw-RadialGlow $g  190  85 240 ([System.Drawing.Color]::FromArgb(105, 0, 120, 255))  ([System.Drawing.Color]::FromArgb(0, 0, 80, 200))
Draw-RadialGlow $g 1495 450 290 ([System.Drawing.Color]::FromArgb( 88, 255, 120, 10)) ([System.Drawing.Color]::FromArgb(0, 200, 80, 0))
Draw-RadialGlow $g 1055  35 165 ([System.Drawing.Color]::FromArgb( 60, 100, 80, 255)) ([System.Drawing.Color]::FromArgb(0, 70, 50, 200))

# ── Icon circle + title text ──
$iconD = 84; $iconX = 50; $iconY = 26
Draw-IconCircle $g $icon $iconX $iconY $iconD
$titleFont  = New-Object System.Drawing.Font('Segoe UI', 50, [System.Drawing.FontStyle]::Bold)
$titleBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
$titleTX = $iconX + $iconD + 20
$titleTY = $iconY + [int](($iconD - $titleFont.GetHeight()) / 2)
$g.DrawString('Keystone OIDC', $titleFont, $titleBrush, $titleTX, $titleTY)
$titleFont.Dispose(); $titleBrush.Dispose()

# ── Tagline ──
$tagY = $iconY + $iconD + 18
$tagFont  = New-Object System.Drawing.Font('Segoe UI', 20, [System.Drawing.FontStyle]::Regular)
$tagBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(200, 175, 212, 255))
$g.DrawString('Turn WordPress into an OIDC Identity Provider', $tagFont, $tagBrush, 54, $tagY)
$tagFont.Dispose(); $tagBrush.Dispose()

# ── Orange accent bar ──
$accentY = $tagY + 37
$ap1 = New-Object System.Drawing.PointF(54, $accentY)
$ap2 = New-Object System.Drawing.PointF(350, $accentY)
$accentBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    $ap1, $ap2,
    [System.Drawing.Color]::FromArgb(255, 255, 138, 0),
    [System.Drawing.Color]::FromArgb(0,   255, 138, 0))
$g.FillRectangle($accentBrush, 54, $accentY, 296, 3)
$accentBrush.Dispose()

# ── Feature chips – 2 columns × 3 rows ──
# Each chip is 370 px wide. Col 0 starts at X=54, Col 1 at X=54+370+22=446.
# Col 1 ends at 446+370=816. Screenshot card starts at X=882 → 66 px gap. 
$features = @(
    'Authorization Code + PKCE',
    'RS256 JWT + ID Tokens',
    'Discovery Endpoint + JWKS',
    'Refresh Token Rotation',
    'Consent Screen + Scopes',
    'Unlimited Clients'
)
$chipFont = New-Object System.Drawing.Font('Segoe UI Semibold', 17, [System.Drawing.FontStyle]::Regular)
$cX0 = 54;  $cY0 = $accentY + 26
$cW  = 370; $cH  = 50
$cGX = 22;  $cGY = 14

for ($i = 0; $i -lt $features.Count; $i++) {
    $col = $i % 2
    $row = [int][Math]::Floor($i / 2)
    Draw-Chip $g ($cX0 + $col * ($cW + $cGX)) ($cY0 + $row * ($cH + $cGY)) $cW $cH $features[$i] $chipFont
}
$chipFont.Dispose()

# ── Screenshot card  (right side, X=882..1502, Y=26..474) ──
Draw-ScreenshotCard $g $shot 882 26 620 448 22

$bmp.Save($outLarge, [System.Drawing.Imaging.ImageFormat]::Png)
Write-Host "Saved: $outLarge"

# ── SMALL BANNER  772 × 250 ──────────────────────────────────────────────────
$SW = 772; $SH = 250
$sbmp = New-Object System.Drawing.Bitmap($SW, $SH)
$sg   = [System.Drawing.Graphics]::FromImage($sbmp)
$sg.SmoothingMode      = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$sg.InterpolationMode  = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$sg.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
$sg.PixelOffsetMode    = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$sg.TextRenderingHint  = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

# Background
$sBgBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    (New-Object System.Drawing.Rectangle(0, 0, $SW, $SH)),
    [System.Drawing.Color]::FromArgb(255,  4, 12, 34),
    [System.Drawing.Color]::FromArgb(255,  8, 44, 94),
    [System.Drawing.Drawing2D.LinearGradientMode]::Horizontal)
$sg.FillRectangle($sBgBrush, 0, 0, $SW, $SH)
$sBgBrush.Dispose()

# Diagonal lines
$sLinePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(15, 255, 255, 255), 1)
for ($i = -250; $i -lt 920; $i += 30) { $sg.DrawLine($sLinePen, $i, 0, $i + 155, $SH) }
$sLinePen.Dispose()

# Glows
Draw-RadialGlow $sg 105 58 135 ([System.Drawing.Color]::FromArgb(105, 0, 120, 255))  ([System.Drawing.Color]::FromArgb(0, 0, 80, 200))
Draw-RadialGlow $sg 730 215 140 ([System.Drawing.Color]::FromArgb( 88, 255, 120, 10)) ([System.Drawing.Color]::FromArgb(0, 200, 80, 0))

# Icon circle + title text
$sIconD = 54; $sIconX = 24; $sIconY = 14
Draw-IconCircle $sg $icon $sIconX $sIconY $sIconD
$sTitleFont  = New-Object System.Drawing.Font('Segoe UI', 28, [System.Drawing.FontStyle]::Bold)
$sTitleBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
$sTitleTX = $sIconX + $sIconD + 12
$sTitleTY = $sIconY + [int](($sIconD - $sTitleFont.GetHeight()) / 2)
$sg.DrawString('Keystone OIDC', $sTitleFont, $sTitleBrush, $sTitleTX, $sTitleTY)
$sTitleFont.Dispose(); $sTitleBrush.Dispose()

# Tagline
$sTagY = $sIconY + $sIconD + 12
$sTagFont  = New-Object System.Drawing.Font('Segoe UI', 11, [System.Drawing.FontStyle]::Regular)
$sTagBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(200, 175, 212, 255))
$sg.DrawString('OIDC Identity Provider for WordPress', $sTagFont, $sTagBrush, 28, $sTagY)
$sTagFont.Dispose(); $sTagBrush.Dispose()

# Accent bar
$sAccentY   = $sTagY + 21
$sAp1 = New-Object System.Drawing.PointF(28, $sAccentY)
$sAp2 = New-Object System.Drawing.PointF(210, $sAccentY)
$sAccBrush = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
    $sAp1, $sAp2,
    [System.Drawing.Color]::FromArgb(255, 255, 138, 0),
    [System.Drawing.Color]::FromArgb(0,   255, 138, 0))
$sg.FillRectangle($sAccBrush, 28, $sAccentY, 182, 2)
$sAccBrush.Dispose()

# Feature chips – 4 features, 2 columns × 2 rows
# chipW=190, gap=10. Col0 X=24, Col1 X=224. Col1 right edge=414. Card starts X=448 → 34px gap.
$sFeatures = @('Auth Code + PKCE', 'RS256 JWT Tokens', 'Discovery + JWKS', 'Unlimited Clients')
$sChipFont = New-Object System.Drawing.Font('Segoe UI Semibold', 10.5, [System.Drawing.FontStyle]::Regular)
$scX0 = 24;  $scY0 = $sAccentY + 12
$scW  = 190; $scH  = 30
$scGX = 10;  $scGY = 8

for ($i = 0; $i -lt $sFeatures.Count; $i++) {
    $col = $i % 2
    $row = [int][Math]::Floor($i / 2)
    Draw-Chip $sg ($scX0 + $col * ($scW + $scGX)) ($scY0 + $row * ($scH + $scGY)) $scW $scH $sFeatures[$i] $sChipFont
}
$sChipFont.Dispose()

# Screenshot card  (X=448..756, Y=13..237)
Draw-ScreenshotCard $sg $shot 448 13 308 224 14

$sbmp.Save($outSmall, [System.Drawing.Imaging.ImageFormat]::Png)
Write-Host "Saved: $outSmall"

# ── Cleanup ───────────────────────────────────────────────────────────────────
$g.Dispose();  $bmp.Dispose()
$sg.Dispose(); $sbmp.Dispose()
$icon.Dispose(); $shot.Dispose()
Write-Host "Done."

