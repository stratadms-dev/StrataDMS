<?php
/**
 * StrataDMS
 * Copyright (C) 2026 James Briscoe
 *
 * StrataDMS is free software; You can redistribute it and/or modify it under the terms of:
 *   - the GNU Affero General Public License version 3 as published by the Free Software Foundation.
 *
 * StrataDMS is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

session_start();
require_once __DIR__ . '/../../src/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit("Unauthorized");
}

$document_id = $_GET['document_id'] ?? 0;

if (!$document_id) {
    http_response_code(400);
    exit("Missing document_id");
}

$userId = $_SESSION['user_id'];

try {
    // Fetch document
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE id = :id");
    $stmt->execute(['id' => $document_id]);
    $doc = $stmt->fetch();

    if (!$doc) throw new Exception("Document not found");

    $originalFilename = $doc['filename'];
    $originalPath = __DIR__ . '/../../storage/documents/' . $originalFilename;

    if (!file_exists($originalPath)) throw new Exception("Original file not found on disk");

    // Fetch all stamp placements
    $stmtS = $pdo->prepare("SELECT ds.page_num, ds.pos_x, ds.pos_y, s.name, s.stamp_text, s.font, s.font_size, s.color 
                            FROM document_stamps ds 
                            JOIN stamps s ON ds.stamp_id = s.id 
                            WHERE ds.document_id = :did");
    $stmtS->execute(['did' => $document_id]);
    $placements = $stmtS->fetchAll();

    // Fetch all annotations
    $stmtA = $pdo->prepare("SELECT type, page_num, pos_x, pos_y, width, height, color 
                            FROM document_annotations 
                            WHERE document_id = :did");
    $stmtA->execute(['did' => $document_id]);
    $annotations = $stmtA->fetchAll();

    // Fetch watermarks
    $stmtW = $pdo->prepare("SELECT * FROM watermarks WHERE (document_id = :did OR document_id IS NULL) AND is_active = true ORDER BY document_id DESC NULLS LAST LIMIT 1");
    $stmtW->execute(['did' => $document_id]);
    $watermark = $stmtW->fetch();

    $disp = (isset($_GET['action']) && $_GET['action'] === 'inline') ? 'inline' : 'attachment';
    if (empty($placements) && empty($annotations) && empty($watermark)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disp . '; filename="' . htmlspecialchars($doc['title']) . '.pdf"');
        readfile($originalPath);
        exit;
    }

    $psByPage = [];
    
    // Process stamps
    foreach ($placements as $p) {
        $pageNum = (int)$p['page_num'];
        if (!isset($psByPage[$pageNum])) $psByPage[$pageNum] = [];
        
        $hex = ltrim($p['color'], '#');
        if (strlen($hex) == 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2)) / 255;
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2)) / 255;
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2)) / 255;
        } else {
            $r = hexdec(substr($hex, 0, 2)) / 255;
            $g = hexdec(substr($hex, 2, 2)) / 255;
            $b = hexdec(substr($hex, 4, 2)) / 255;
        }

        $psText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $p['stamp_text']);
        $psFont = $p['font'];
        $psSize = (int)$p['font_size'];
        $x = (float)$p['pos_x'];
        $y = (float)$p['pos_y'];
        
        $psByPage[$pageNum][] = <<<PS
        gsave
        /$psFont findfont $psSize scalefont setfont
        $r $g $b setrgbcolor
        
        currentpagedevice /PageSize get aload pop
        /h exch def
        /w exch def
        
        w $x 100 div mul
        h h $y 100 div mul sub
        translate
        
        ($psText) stringwidth pop 2 div neg
        $psSize 3 div neg
        moveto
        
        ($psText) show
        grestore
PS;
    }

    // Process annotations
    foreach ($annotations as $a) {
        $pageNum = (int)$a['page_num'];
        if (!isset($psByPage[$pageNum])) $psByPage[$pageNum] = [];
        
        $hex = ltrim($a['color'], '#');
        if (strlen($hex) == 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2)) / 255;
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2)) / 255;
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2)) / 255;
        } else {
            $r = hexdec(substr($hex, 0, 2)) / 255;
            $g = hexdec(substr($hex, 2, 2)) / 255;
            $b = hexdec(substr($hex, 4, 2)) / 255;
        }

        $x = (float)$a['pos_x'];
        $y = (float)$a['pos_y'];
        $w_pct = (float)$a['width'];
        $h_pct = (float)$a['height'];
        $type = $a['type'];
        
        $transparencyCmd = ($type === 'highlight') ? "0.4 .setfillconstantalpha /Multiply .setblendmode" : "";
        
        $psByPage[$pageNum][] = <<<PS
        gsave
        currentpagedevice /PageSize get aload pop
        /h exch def
        /w exch def
        $r $g $b setrgbcolor
        $transparencyCmd
        
        w $x 100 div mul
        h 100 $y sub $h_pct sub 100 div mul
        w $w_pct 100 div mul
        h $h_pct 100 div mul
        rectfill
        grestore
PS;
    }

    $watermarkPs = "";
    $imageWatermarkPdf = null;
    
    if (!empty($watermark)) {
        if (!empty($watermark['image_filename'])) {
            $imgFile = __DIR__ . '/../../storage/watermarks/' . $watermark['image_filename'];
            if (file_exists($imgFile)) {
                $rot = (int)$watermark['rotation'];
                $opacity = (float)($watermark['opacity'] / 100);
                $size_pct = (int)$watermark['size_pct'];
                
                $targetWidth = 612 * ($size_pct / 100); // Base off 8.5x11 inches
                
                $offsetX = (int)$watermark['offset_x'];
                $offsetY = (int)$watermark['offset_y'];
                
                $h_pos = $watermark['h_pos'];
                $v_pos = $watermark['v_pos'];
                
                // Calculate percentage position (matching CSS logic)
                $x_pct = ($h_pos === 'center') ? 50 : (($h_pos === 'left') ? max(5, $size_pct / 2) : min(95, 100 - $size_pct / 2));
                $y_pct = ($v_pos === 'center') ? 50 : (($v_pos === 'top') ? 10 : 90);
                
                $x_pct += $offsetX;
                $y_pct += $offsetY;
                
                $imageWatermarkPdf = tempnam(sys_get_temp_dir(), 'wm_stamp_') . '.pdf';
                
                if (class_exists('Imagick')) {
                    try {
                        $image = new Imagick($imgFile);
                        $image->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
                        $image->resizeImage($targetWidth, 0, Imagick::FILTER_LANCZOS, 1);
                        
                        if ($rot !== 0) {
                            // Negate the rotation because Imagick rotates clockwise for positive values,
                            // while the frontend and PostScript rotate counter-clockwise for positive values.
                            $image->rotateImage(new ImagickPixel('transparent'), -$rot);
                        }
                        
                        $canvas = new Imagick();
                        $canvas->newImage(612, 792, new ImagickPixel('transparent'));
                        
                        $w = $image->getImageWidth();
                        $h = $image->getImageHeight();
                        
                        $x = (612 * ($x_pct / 100)) - ($w / 2);
                        $y = (792 * ($y_pct / 100)) - ($h / 2);
                        
                        $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, $x, $y);
                        $canvas->setImageFormat('pdf');
                        $canvas->writeImage($imageWatermarkPdf);
                        
                        $image->clear();
                        $canvas->clear();
                    } catch (Exception $e) {
                        error_log("Imagick failed: " . $e->getMessage());
                    }
                }
            }
        } else {
            // Parse macros in watermark text
            $text = $watermark['text'];
            $text = str_replace('%(Id)', $doc['id'], $text);
            $text = str_replace('%(Date)', date('Y-m-d'), $text);
            $text = str_replace('%(User)', 'System', $text); // In real app, user username
            $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
            
            $h_pos = $watermark['h_pos'];
            $v_pos = $watermark['v_pos'];
            $rot = (int)$watermark['rotation'];
            $opacity = (float)($watermark['opacity'] / 100);
            $size_pct = (int)$watermark['size_pct'];
            
            $offsetX = (int)$watermark['offset_x'];
            $offsetY = (int)$watermark['offset_y'];
            
            $x_pct = ($h_pos === 'center') ? 50 : (($h_pos === 'left') ? max(5, $size_pct / 2) : min(95, 100 - $size_pct / 2));
            $y_pct = ($v_pos === 'center') ? 50 : (($v_pos === 'top') ? 10 : 90);
            
            $x_pct += $offsetX;
            $y_pct += $offsetY;
            
            $x_factor = $x_pct / 100;
            // PostScript Y-axis starts from the bottom, so we invert the Y percentage
            $y_factor = 1 - ($y_pct / 100);
            
            // Font sizing logic: if size_pct is 50%, then the text should span roughly 50% of the page width
            // A rough approximation in PS: font size = page_width * size_pct / 100 / (text_length * 0.5)
            // We'll calculate font size dynamically in PS
        
        $watermarkPs = <<<PS
        gsave
        currentpagedevice /PageSize get aload pop
        /h exch def
        /w exch def
        
        % Calculate font size so it takes up size_pct of the page width
        /Helvetica-Bold findfont 100 scalefont setfont
        ($text) stringwidth pop /tw exch def
        w $size_pct 100 div mul tw div 100 mul /fs exch def
        /Helvetica-Bold findfont fs scalefont setfont
        
        0 0 0 setrgbcolor
        $opacity .setfillconstantalpha
        
        w $x_factor mul
        h $y_factor mul
        translate
        
        $rot rotate
        
        ($text) stringwidth pop 2 div neg
        fs 0.35 mul neg
        moveto
        
        ($text) show
        grestore
PS;
        }
    }

    $psContent = "<<\n  /EndPage\n  {\n    2 eq { pop false }\n    {\n";
    $psContent .= "        /pcount exch def\n"; 
    $psContent .= "        pcount 1 add /pnum exch def\n"; 
    
    foreach ($psByPage as $pageNum => $snippets) {
        $psContent .= "        pnum $pageNum eq {\n";
        foreach ($snippets as $snippet) {
            $psContent .= "$snippet\n";
        }
        $psContent .= "        } if\n";
    }
    
    if (!empty($watermarkPs)) {
        $psContent .= $watermarkPs . "\n";
    }
    
    $psContent .= "        true\n    } ifelse\n  } bind\n>> setpagedevice\n";

    $psFile = tempnam(sys_get_temp_dir(), 'stamps_') . '.ps';
    file_put_contents($psFile, $psContent);
    $cmdTotal = sprintf("gs -q -dNOSAFER -dNODISPLAY -c \"(%s) (r) file runpdfbegin pdfpagecount = quit\"", $originalPath);
    exec($cmdTotal, $gsOutput, $gsRet);
    
    $totalPages = 0;
    if ($gsRet === 0 && is_array($gsOutput)) {
        foreach($gsOutput as $line) {
            $line = trim($line);
            if (is_numeric($line) && (int)$line > 0) {
                $totalPages = (int)$line;
                break;
            }
        }
    }
    
    if ($totalPages <= 0) {
        throw new Exception("Failed to read PDF page count. Ret: " . $gsRet . ". Output: " . implode(" | ", $gsOutput) . ". Cmd: " . $cmdTotal);
    }

    $tempFiles = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        $tmp = tempnam(sys_get_temp_dir(), 'p_') . '.pdf';
        if (isset($psByPage[$i])) {
            $cmd = sprintf(
                "gs -sDEVICE=pdfwrite -dALLOWPSTRANSPARENCY -dNOPAUSE -dBATCH -dSAFER -q -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s %s",
                $i, $i, escapeshellarg($tmp), escapeshellarg($psFile), escapeshellarg($originalPath)
            );
        } else {
            $cmd = sprintf(
                "gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s",
                $i, $i, escapeshellarg($tmp), escapeshellarg($originalPath)
            );
        }
        exec($cmd, $out, $ret);
        if ($ret !== 0 || !file_exists($tmp)) throw new Exception("Ghostscript failed to extract/stamp page $i.");
        $tempFiles[] = $tmp;
    }

    $modifiedPath = tempnam(sys_get_temp_dir(), 'exported_') . '.pdf';
    $mergeArgs = array_map('escapeshellarg', $tempFiles);
    $cmdMerge = sprintf("gs -sDEVICE=pdfwrite -dNOPAUSE -dBATCH -dSAFER -q -sOutputFile=%s %s", escapeshellarg($modifiedPath), implode(' ', $mergeArgs));
    exec($cmdMerge, $outRem, $retRem);
    
    foreach ($tempFiles as $tmp) @unlink($tmp);
    @unlink($psFile);

    if ($retRem !== 0 || !file_exists($modifiedPath)) throw new Exception("Ghostscript failed to rebuild PDF.");
    
    // Apply image watermark via pdftk if present
    if ($imageWatermarkPdf && file_exists($imageWatermarkPdf)) {
        $stampedFinal = tempnam(sys_get_temp_dir(), 'final_wm_') . '.pdf';
        $pdftkCmd = sprintf(
            "pdftk %s multistamp %s output %s",
            escapeshellarg($modifiedPath),
            escapeshellarg($imageWatermarkPdf),
            escapeshellarg($stampedFinal)
        );
        exec($pdftkCmd, $pdftkOut, $pdftkRet);
        if ($pdftkRet === 0 && file_exists($stampedFinal)) {
            @unlink($modifiedPath);
            $modifiedPath = $stampedFinal;
        }
        @unlink($imageWatermarkPdf);
    }

    $disp = (isset($_GET['action']) && $_GET['action'] === 'inline') ? 'inline' : 'attachment';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disp . '; filename="Stamped_' . htmlspecialchars($doc['title']) . '.pdf"');
    header('Content-Length: ' . filesize($modifiedPath));
    readfile($modifiedPath);
    @unlink($modifiedPath);
    exit;

} catch (Exception $e) {
    if (isset($tempFiles) && is_array($tempFiles)) {
        foreach ($tempFiles as $tmp) @unlink($tmp);
    }
    if (isset($psFile)) @unlink($psFile);
    if (isset($modifiedPath)) @unlink($modifiedPath);
    http_response_code(500);
    exit("Error: " . $e->getMessage());
}
?>
