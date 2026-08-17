<?php

/**
 * CvParser — extraction de texte et de compétences depuis un CV (PDF/DOCX),
 * en PHP pur (pas de Composer, cohérent avec le reste du backend).
 *
 * Portée volontairement limitée pour un projet de stage :
 *  - DOCX : lecture fiable via ZipArchive (extension PHP standard) du
 *    fragment word/document.xml.
 *  - PDF  : extraction "best effort" par inspection directe des flux de
 *    contenu (stream/endstream), sans dépendance externe. Fonctionne bien
 *    sur des PDF texte standards (générés par Word, LibreOffice, Canva...),
 *    moins bien sur des PDF scannés (image) ou avec polices custom/CID —
 *    dans ce cas l'extraction renvoie un texte partiel. C'est acceptable
 *    ici : on retombe simplement sur moins de compétences détectées,
 *    plutôt que sur une erreur bloquante pour le candidat.
 */
class CvParser
{
    /** Référentiel de compétences reconnues — mots-clés recherchés dans le texte du CV */
    private const SKILLS_TAXONOMY = [
        'PHP', 'JavaScript', 'TypeScript', 'Python', 'Java', 'C++', 'C#', 'Go', 'Rust',
        'HTML', 'CSS', 'Tailwind', 'Bootstrap', 'Sass',
        'React', 'Vue.js', 'Angular', 'Next.js', 'Node.js', 'Express',
        'Laravel', 'Symfony', 'Django', 'Flask', 'Spring', '.NET',
        'MySQL', 'PostgreSQL', 'MongoDB', 'SQLite', 'Redis', 'SQL',
        'Docker', 'Kubernetes', 'AWS', 'Azure', 'GCP', 'CI/CD', 'Jenkins',
        'Git', 'GitHub', 'GitLab', 'Linux', 'Bash',
        'REST', 'GraphQL', 'JWT', 'OAuth',
        'Machine Learning', 'Deep Learning', 'NLP', 'TensorFlow', 'PyTorch', 'Pandas', 'NumPy',
        'Scrum', 'Agile', 'Kanban', 'JIRA',
        'Figma', 'UI/UX', 'Photoshop',
        'Excel', 'Power BI', 'Tableau', 'ETL',
    ];

    public static function extractText(string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'docx'  => self::extractFromDocx($path),
            'pdf'   => self::extractFromPdf($path),
            default => '',
        };
    }

    private static function extractFromDocx(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return ''; // extension zip non activée sur ce serveur PHP
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        // Les fins de paragraphe/ligne Word doivent devenir des espaces
        // avant de retirer les balises, sinon les mots collent entre eux.
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:tab/>'], ' ', $xml);
        $text = strip_tags($xml);
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function extractFromPdf(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return '';
        }

        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                $content = $decoded !== false ? $decoded : $stream;
                $text .= self::extractTextOperators($content) . ' ';
            }
        }

        return self::sanitizeUtf8(trim($text));
    }
 private static function sanitizeUtf8(string $text): string
{
    if (mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }
    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    return $clean !== false ? $clean : preg_replace('/[^\x20-\x7E\n\r\t]/', '', $text);
}
    /** Récupère le texte des opérateurs PDF Tj / TJ : (texte) Tj  ou  [(a)(b)] TJ */
    private static function extractTextOperators(string $content): string
    {
        $out = [];

        if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj/', $content, $m)) {
            foreach ($m[0] as $chunk) {
                $out[] = self::unescapePdfString(substr($chunk, 0, strrpos($chunk, ')') + 1));
            }
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $m)) {
            foreach ($m[1] as $array) {
                if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $array, $strs)) {
                    foreach ($strs[0] as $s) {
                        $out[] = self::unescapePdfString($s);
                    }
                }
            }
        }

        return implode(' ', $out);
    }

    private static function unescapePdfString(string $s): string
    {
        $s = substr($s, 1, -1); // retire les parenthèses englobantes
        return str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $s);
    }

    /** Compare le texte du CV au référentiel de compétences, mot entier, insensible à la casse */
    public static function extractSkills(string $text): array
    {
        $found = [];
        foreach (self::SKILLS_TAXONOMY as $skill) {
            $pattern = '/(?<![a-z0-9])' . preg_quote($skill, '/') . '(?![a-z0-9])/i';
            if (preg_match($pattern, $text)) {
                $found[] = $skill;
            }
        }
        return $found;
    }

    /** Heuristique simple : cherche "X ans/années d'expérience" ou "X years of experience" */
    public static function guessYearsExperience(string $text): ?int
    {
        if (preg_match('/(\d{1,2})\s*(?:ans|années)\s+d[\'’]exp[ée]rience/iu', $text, $m)) {
            return min(50, (int) $m[1]);
        }
        if (preg_match('/(\d{1,2})\+?\s*years?\s+(?:of\s+)?experience/i', $text, $m)) {
            return min(50, (int) $m[1]);
        }
        return null;
    }
}
