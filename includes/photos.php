<?php
// Traitement des photos envoyées depuis l'admin.
// Chaque image est ré-encodée en JPEG (ce qui supprime les métadonnées EXIF, dont le GPS),
// redressée selon l'orientation EXIF, puis déclinée en « full » et « thumb ».

/**
 * Normalise $_FILES['x'] (multiple) en liste de fichiers.
 */
function fichiers_envoyes(string $champ): array
{
    $f = $_FILES[$champ] ?? null;
    if (!$f || !is_array($f['name'])) {
        return [];
    }
    $out = [];
    foreach ($f['name'] as $i => $nom) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $nom, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i]];
    }
    return $out;
}

/**
 * Enregistre les photos envoyées et les rattache à une carte ou un swap.
 * @param 'carte'|'swap' $type
 * @return string[] erreurs éventuelles
 */
function enregistrer_photos(string $type, int $id, array $fichiers, bool $publique): array
{
    $erreurs = [];
    $col = $type === 'swap' ? 'swap_id' : 'carte_id';
    $st = db()->prepare("SELECT COALESCE(MAX(ordre), 0) FROM photos WHERE $col = ?");
    $st->execute([$id]);
    $ordre = (int) $st->fetchColumn();

    $ins = db()->prepare("INSERT INTO photos ($col, fichier, largeur, hauteur, publique, ordre) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($fichiers as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = $f['name'] . ' : échec de l\'envoi (code ' . $f['error'] . ', fichier trop gros ?)';
            continue;
        }
        try {
            [$nom, $w, $hh] = traiter_image($f['tmp_name']);
            $ins->execute([$id, $nom, $w, $hh, $publique ? 1 : 0, ++$ordre]);
        } catch (Throwable $e) {
            $erreurs[] = $f['name'] . ' : ' . $e->getMessage();
        }
    }
    return $erreurs;
}

/** @return array{0:string,1:int,2:int} [nom de fichier, largeur, hauteur] */
function traiter_image(string $chemin): array
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($chemin);
    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($chemin),
        'image/png' => imagecreatefrompng($chemin),
        'image/webp' => imagecreatefromwebp($chemin),
        default => throw new RuntimeException("format non pris en charge ($mime) — JPEG, PNG ou WebP"),
    };
    if (!$img) {
        throw new RuntimeException('image illisible');
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($chemin);
        $img = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    $nom = bin2hex(random_bytes(16)) . '.jpg';
    [$w, $hh] = redimensionner_jpeg($img, PHOTO_MAX, UPLOAD_DIR . '/full/' . $nom, 86);
    redimensionner_jpeg($img, THUMB_MAX, UPLOAD_DIR . '/thumbs/' . $nom, 80);
    return [$nom, $w, $hh];
}

/** @return array{0:int,1:int} dimensions finales */
function redimensionner_jpeg(GdImage $src, int $max, string $dest, int $qualite): array
{
    $w = imagesx($src);
    $hh = imagesy($src);
    $r = min(1, $max / max($w, $hh));
    $nw = max(1, (int) round($w * $r));
    $nh = max(1, (int) round($hh * $r));

    $dst = imagecreatetruecolor($nw, $nh);
    // fond blanc pour les PNG/WebP transparents
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hh);
    imageinterlace($dst, true);
    if (!imagejpeg($dst, $dest, $qualite)) {
        throw new RuntimeException('écriture impossible dans ' . dirname($dest) . ' (droits ?)');
    }
    return [$nw, $nh];
}

function supprimer_fichiers_photo(string $fichier): void
{
    // basename : défense en profondeur, le nom vient de la base
    $fichier = basename($fichier);
    @unlink(UPLOAD_DIR . '/full/' . $fichier);
    @unlink(UPLOAD_DIR . '/thumbs/' . $fichier);
}

/** Supprime les fichiers de toutes les photos d'un objet (avant sa suppression en base, la cascade gère les lignes). */
function supprimer_photos_de(string $type, int $id): void
{
    foreach (photos_de($type, $id, false) as $p) {
        supprimer_fichiers_photo($p['fichier']);
    }
}
