<?php

use PHPUnit\Framework\TestCase;

/**
 * CvParserTest — vérifie l'extraction de texte/compétences sur de vrais
 * fichiers PDF et DOCX (générés en mémoire dans setUp, pas de binaire
 * versionné dans le repo).
 */
final class CvParserTest extends TestCase
{
    private string $docxPath;
    private string $pdfPath;

    protected function setUp(): void
    {
        $this->docxPath = sys_get_temp_dir() . '/cvparser_test.docx';
        $this->pdfPath = sys_get_temp_dir() . '/cvparser_test.pdf';

        $this->makeDocx($this->docxPath, [
            "Mayna Ben Salah",
            "Développeuse PHP et JavaScript, experte React et Docker.",
            "3 ans d'expérience en développement web.",
            "Compétences: MySQL, Git, Tailwind, Laravel.",
        ]);

        $this->makePdf($this->pdfPath, [
            "Mayna Ben Salah",
            "Python Java React 5 years experience",
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->docxPath);
        @unlink($this->pdfPath);
    }

    public function testExtractTextFromDocx(): void
    {
        $text = CvParser::extractText($this->docxPath, 'docx');

        $this->assertStringContainsString('Mayna Ben Salah', $text);
        $this->assertStringContainsString('React', $text);
    }

    public function testExtractSkillsFromDocx(): void
    {
        $text = CvParser::extractText($this->docxPath, 'docx');
        $skills = CvParser::extractSkills($text);

        foreach (['PHP', 'JavaScript', 'React', 'Docker', 'MySQL', 'Git', 'Tailwind', 'Laravel'] as $expected) {
            $this->assertContains($expected, $skills);
        }
    }

    public function testGuessYearsExperienceFrench(): void
    {
        $text = CvParser::extractText($this->docxPath, 'docx');
        $this->assertSame(3, CvParser::guessYearsExperience($text));
    }

    public function testExtractTextFromPdf(): void
    {
        $text = CvParser::extractText($this->pdfPath, 'pdf');

        $this->assertStringContainsString('Mayna Ben Salah', $text);
        $this->assertStringContainsString('Python', $text);
    }

    public function testExtractSkillsFromPdf(): void
    {
        $text = CvParser::extractText($this->pdfPath, 'pdf');
        $skills = CvParser::extractSkills($text);

        $this->assertContains('Python', $skills);
        $this->assertContains('Java', $skills);
        $this->assertContains('React', $skills);
    }

    public function testGuessYearsExperienceEnglish(): void
    {
        $text = CvParser::extractText($this->pdfPath, 'pdf');
        $this->assertSame(5, CvParser::guessYearsExperience($text));
    }

    public function testUnsupportedExtensionReturnsEmptyString(): void
    {
        $this->assertSame('', CvParser::extractText($this->pdfPath, 'txt'));
    }

    public function testNoExperienceMentionReturnsNull(): void
    {
        $this->assertNull(CvParser::guessYearsExperience('Aucune mention de durée ici.'));
    }

    /** Construit un .docx minimal valide (juste ce que ZipArchive/Word ont besoin de lire) */
    private function makeDocx(string $path, array $paragraphs): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>');

        $body = '';
        foreach ($paragraphs as $p) {
            $body .= '<w:p><w:r><w:t>' . htmlspecialchars($p, ENT_XML1) . '</w:t></w:r></w:p>';
        }

        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $body
            . '</w:body></w:document>');

        $zip->close();
    }

    /** Construit un .pdf minimal valide, contenu non compressé, un Tj par ligne */
    private function makePdf(string $path, array $lines): void
    {
        $content = '';
        $y = 700;
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $content .= "BT /F1 12 Tf 72 $y Td ($escaped) Tj ET\n";
            $y -= 20;
        }

        $objects = [
            1 => "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            2 => "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            3 => "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n",
            4 => "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            5 => "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $obj;
        }
        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";

        file_put_contents($path, $pdf);
    }
}
