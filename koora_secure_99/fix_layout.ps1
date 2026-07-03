$file = "index.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

$oldFlex1 = '                             <td style="padding:18px 25px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:8px; min-width:120px; justify-content:flex-end;">
                                        <span style="font-weight:700; font-size:14px; color:var(--text-main);"><?php echo $m[''homeTeam'']; ?></span>
                                        <img src="<?php echo $m[''homeLogo'']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                    </div>
                                    <span style="background:var(--bg-main); padding:3px 10px; border-radius:6px; color:var(--text-main); font-size:12px; font-weight:800; min-width:45px; text-align:center; border:1px solid var(--border-color);">
                                        <?php echo (!empty($m[''score'']) && strtolower($m[''score'']) !== ''vs'') ? $m[''score''] : ''VS''; ?>
                                    </span>
                                    <div style="display:flex; align-items:center; gap:8px; min-width:120px;">
                                        <img src="<?php echo $m[''awayLogo'']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                        <span style="font-weight:700; font-size:14px; color:var(--text-main);"><?php echo $m[''awayTeam'']; ?></span>
                                    </div>
                                </div>
                             </td>'

$newGrid = '                             <td style="padding:12px 25px;">
                                <div style="display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px;">
                                    <div style="display:flex; align-items:center; gap:8px; justify-content:flex-end;">
                                        <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo $m[''homeTeam'']; ?></span>
                                        <img src="<?php echo $m[''homeLogo'']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                    </div>
                                    <span style="background:var(--bg-main); padding:4px 10px; border-radius:8px; color:var(--text-main); font-size:13px; font-weight:800; min-width:52px; text-align:center; border:1px solid var(--border-color); white-space:nowrap;">
                                        <?php $sc=trim($m[''score'']??''''); echo(empty($sc)||$sc===''- ''||strtolower($sc)===''vs'')?''VS'':$sc; ?>
                                    </span>
                                    <div style="display:flex; align-items:center; gap:8px; justify-content:flex-start;">
                                        <img src="<?php echo $m[''awayLogo'']; ?>" style="width:26px; height:26px; object-fit:contain; flex-shrink:0;">
                                        <span style="font-weight:700; font-size:13px; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:145px;"><?php echo $m[''awayTeam'']; ?></span>
                                    </div>
                                </div>
                             </td>'

$count = ([regex]::Matches($content, [regex]::Escape($oldFlex1))).Count
Write-Host "Found $count occurrences to replace"

if ($count -gt 0) {
    $content = $content.Replace($oldFlex1, $newGrid)
    [System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
    Write-Host "Done! Replaced $count occurrences."
} else {
    Write-Host "Pattern not found. Checking file..."
    $lines = $content -split "`n"
    $lines[269..270] | ForEach-Object { Write-Host "[$_]" }
}
