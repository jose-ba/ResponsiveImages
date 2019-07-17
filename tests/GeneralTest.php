<?php /** @noinspection PhpStaticAsDynamicMethodCallInspection */

declare(strict_types=1);

use JoseBa\ResponsiveImages;
use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__) . '/../vendor/autoload.php';

class GeneralTest extends TestCase
{

    /** @var ResponsiveImages */
    protected $responsiveImages;
    protected $rutaMiniaturas;

    public function setUp(): void
    {
        $this->rutaMiniaturas = dirname(__FILE__) . '/../thumbs/';
        $this->responsiveImages = new ResponsiveImages(
            'http://localhost/ResponsiveImages/thumbs/',
            $this->rutaMiniaturas,
            20
        );
        parent::setUp();
    }

    /**
     * Compruebo que se genera el número esperado de miniaturas.
     *
     * La imagen de prueba (tests/test-image.jpg) ocupa 112,3kb y mide 1800x1196px
     */
    public function testSeGeneraElNumeroEsperadoDeMiniaturas()
    {
        $this->borrarTodasLasMiniaturas();
        $sizes = [
            500 => 100,
            980 => 50
        ];
        $this->responsiveImages->generateImg(dirname(__FILE__).'/test-image.jpg', $sizes);
        // Habrá que cambiar la ruta probablemente, en principio sólo si cambia la ruta del archivo de pruebas.
        $miniaturasGeneradas = glob(realpath($this->rutaMiniaturas.'/b1/e6/').'/*.jpg');
        // Serían 9 miniaturas + la imagen original = 10 (el tamaño por defecto del salto es de 200 px)
        $this->assertCount(10, $miniaturasGeneradas);
    }

    public function testSeGeneraElNumeroEsperadoDeMiniaturasSiSeEspecificaTamanoDePaso()
    {
        $this->borrarTodasLasMiniaturas();
        $sizes = [
            500 => 100,
            980 => 50
        ];
        $this->responsiveImages->generateImg(dirname(__FILE__).'/test-image.jpg', $sizes,'',100);
        // Habrá que cambiar la ruta probablemente, en principio sólo si cambia la ruta del archivo de pruebas.
        $miniaturasGeneradas = glob(realpath($this->rutaMiniaturas.'/b1/e6/').'/*.jpg');
        // Serían 9 miniaturas + la imagen original = 10 (el tamaño por defecto del salto es de 200 px)
        $this->assertCount(19, $miniaturasGeneradas);
    }

    private function borrarTodasLasMiniaturas()
    {
        $files = glob($this->rutaMiniaturas.'/b1/e6/*.jpg');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
