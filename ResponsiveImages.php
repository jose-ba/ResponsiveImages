<?php

declare(strict_types=1);

namespace JoseBa;

use Exception;

/**
 * Módulo para generar imagenes responsive.
 */
class ResponsiveImages
{

    const DEFAULT_STEP_SIZE = 200;
    /**
     * @var string
     */
    private $thumbsFolderUrl;
    /**
     * @var string
     */
    private $thumbsFolderPath;
    /**
     * @var int
     */
    private $sizeStepInPx; // (px)

    protected $thumbs = [];

    /**
     * @param string $thumbsFolderUrl Url desde la que será accesible la carpeta en la que se generan las miniaturas.
     * @param string $thumbsFolderPath Ruta a la carpeta de las miniaturas.
     * @param int $sizeStepInPx Tamaño en px que tendrá que haber de diferencia entre un tamaño el inmediatamente
     *                          inferior para que se genere una nueva miniatura (si es 100, se harían imágenes de
     *                          100px, 200px, 3000px...)
     */
    public function __construct(
        string $thumbsFolderUrl,
        string $thumbsFolderPath,
        $sizeStepInPx = self::DEFAULT_STEP_SIZE
    ) {
        $this->thumbsFolderUrl = $thumbsFolderUrl;
        $this->thumbsFolderPath = realpath($thumbsFolderPath) . '/';
        $this->sizeStepInPx = $sizeStepInPx;
    }

    /**
     * Función que genera la etiqueta <img> con todo lo necesario para usar las imágenes responsive.
     * @param string $image Ruta o url de la imagen que se quiere insertar.
     * @param array $sizes Array que se usará para generar el parámetro "sizes" de la imagen responsive. En el array,
     *                     la clave es el ancho máximo del navegador, y el valor el % del ancho del navegador que ocupa
     *                     la imagen hasta ese ancho máximo. Si la clave es "default", el valor será el % del ancho de
     *                     la imagen que se usará si no se encuentra dentro de las otras opciones. Es decir, para este
     *                     array:
     *                     [
     *                        500 => 50
     *                        980 => 33
     *                        'default' => 100
     *                     ]
     *                     Estamos indicando que por debajo de 500px, la imagen ocupa el 50% del ancho (50vw), por
     *                     debajo de 980px, el 33%, y de ahí para arriba, el 100%.
     *
     *                     Es un poco lioso, pero es como funciona la etiqueta img con imágenes responsive.
     * @param string $alt (opcional) Texto alt de la imagen.
     * @param int $sizeStepInPx (opcional) Tamaño del "paso" en px que se usará para generar la imagen (por defecto 200)
     * @return string Devuelve la etiqueta img lista para insertarla en el html.
     * @throws Exception
     */
    public function generateImg(string $image, $sizes, $alt = '', $sizeStepInPx = self::DEFAULT_STEP_SIZE)
    {
        if (empty($sizes)) {
            throw new \Exception("El array sizes no puede estar vacío");
        }

        $output = '<img alt="' . $alt . '" ';
        $this->thumbs = [];
        $imagePath = $this->downloadImage($image);
        $this->generateThumbs($imagePath, $sizeStepInPx);

        $sizes = $this->generateSizesParameter($sizes);
        $srcset = $this->generateSources();
        $output .= $sizes . $srcset . ' >';
        return $output;
    }

    /**
     * Descarga la imagen si lo que llega es una URL.
     * @param string $image Ruta a la imagen o URL.
     * @return string Ruta a la imagen
     */
    private function downloadImage(string $image)
    {
        if (!strpos($image, 'http') === 0) {
            return $image;
        }

        // Es una URL, nos bajamos la imagen y devolvemos la ruta
        $imageContents = file_get_contents($image);
        $imageUrlParts = explode('/', $image);
        $imageFileName = end($imageUrlParts);
        $thumbFolder = $this->getImageFolder($image);
        $outputPath = $thumbFolder . '/' . $imageFileName;
        file_put_contents($outputPath, $imageContents);
        return $outputPath;
    }

    /**
     * Genera el directorio donde se guardará la imagen
     * @param string $imagePath Ruta completa o URL de la imagen
     * @return string
     */
    private function getImageFolder(string $imagePath)
    {
        $pathHash = md5($imagePath);
        $firstLevel = substr($pathHash, 0, 2);
        $secondLevel = substr($pathHash, 2, 2);
        $firstLevelPath = rtrim($this->thumbsFolderPath, '/') . '/' . $firstLevel;
        $secondLevelPath = $firstLevelPath . '/' . $secondLevel;
        if (!is_dir($firstLevelPath)) {
            mkdir($firstLevelPath);
        }
        if (!is_dir($secondLevelPath)) {
            mkdir($secondLevelPath);
        }
        return $secondLevelPath;
    }

    /**
     * Genera los archivos de las miniaturas.
     * @param string $imagePath Ruta de la imagen
     * @param int $sizeStepInPx Tamaño del paso
     * @throws Exception
     */
    private function generateThumbs(string $imagePath, int $sizeStepInPx)
    {
        $imageSize = getimagesize($imagePath);
        $width = $imageSize[0];
        $height = $imageSize[1];
        $thumbSizes = $this->getThumbSizes($width, $height, $sizeStepInPx);
        foreach ($thumbSizes as $size) {
            $this->createThumbFile($imagePath, $size);
        }
    }

    /**
     * Calcula los tamaños necesarios de las miniaturas, a partir del tamaño de la imagen original y el tamaño deseado
     * para el paso entre un thumb y el siguiente.
     * @param $width
     * @param $height
     * @param int $sizeStepInPx
     * @return array Tamaños de las miniaturas a generar.
     */
    private function getThumbSizes($width, $height, int $sizeStepInPx)
    {
        $output = [[$width, $height]];
        $newWidth = $width;
        $newHeight = $height;
        while ($newWidth > 0) {
            $newHeight = $this->calculateNewHeight($newWidth, $newHeight, $sizeStepInPx);
            $newWidth = $newWidth - $sizeStepInPx;
            if ($newWidth > 0 AND $newHeight > 0) {
                $output[] = [$newWidth, $newHeight];
            }
        }
        return $output;
    }

    /**
     * Calcula la altura de la siguiente miniatura, a partir de las dimensiones del tamaño de la actual.
     * @param int $currentWidth Ancho de la imagen actual (última miniatura, por ejemplo).
     * @param int $currentHeight Alto de la imagen actual.
     * @param int $sizeStepInPx
     * @return int Nueva altura
     */
    private function calculateNewHeight($currentWidth, $currentHeight, int $sizeStepInPx)
    {
        $aspectRatio = $currentWidth / $currentHeight;
        $newWidth = $currentWidth - $sizeStepInPx;
        $newHeight = (int)floor($newWidth / $aspectRatio);
        return $newHeight;
    }

    /**
     * @param string $imagePath
     * @param $size
     * @throws Exception
     */
    private function createThumbFile(string $imagePath, $size)
    {
        $originalImagePath = $imagePath;
        $imagePath = realpath($imagePath);
        $fileInfo = getimagesize($imagePath);
        $imagePath = $this->generateThumbPath($imagePath, $size);

        $this->thumbs[] = [
            'path' => $imagePath,
            'width' => $size[0],
            'height' => $size[1]
        ];

        if (file_exists($imagePath)) {
            return;
        }

        $imageFunction = null;
        switch ($fileInfo[2]) {
            case IMAGETYPE_GIF:
                $imageFunction = "ImageGIF";
                $imageCreateFromFunction = "ImageCreateFromGIF";
                break;
            case IMAGETYPE_JPEG:
                $imageFunction = "ImageJPEG";
                $imageCreateFromFunction = "ImageCreateFromJPEG";
                break;
            case IMAGETYPE_PNG:
                $imageFunction = "ImagePNG";
                $imageCreateFromFunction = "ImageCreateFromPNG";
                break;
            default:
                throw new Exception("Tipo de imagen desconocido");
        }

        if ($imageFunction) {
            $originalImage = $imageCreateFromFunction($originalImagePath);
            $thumb = imagecreatetruecolor($size[0], $size[1]);
            imagecopyresized($thumb, $originalImage, 0, 0, 0, 0, $size[0], $size[1], $fileInfo[0],
                $fileInfo[1]);
            $imageFunction($thumb, $imagePath);
        }
    }

    /**
     * Crea la ruta de la miniatura.
     * @param string $imagePath
     * @param $size
     * @return mixed
     */
    private function generateThumbPath(string $imagePath, $size)
    {
        $originalFileName = basename($imagePath);
        $fileNameComponents = explode('.', $originalFileName);
        $newFilename = $fileNameComponents[0] . '_' . $size[0] . 'x' . $size[1] . '.' . $fileNameComponents[1];
        return str_replace($originalFileName, $newFilename, $imagePath);
    }

    /**
     * Genera el atributo "sizes" de la etiqueta img responsive.
     * @param $sizes
     * @return string
     */
    private function generateSizesParameter($sizes)
    {
        $output = " sizes='";
        foreach ($sizes as $maxWidth => $imageWidth) {
            $output .= "(max-width: {$maxWidth}px) {$imageWidth}vw,\n";
        }
        $defaultWidth = isset($sizes['default']) ? $sizes['default'] . 'px' : '100vw';
        $output .= "{$defaultWidth}' ";
        return $output;
    }

    /**
     * Genera el atributo "sources" de la etiqueta img responsive.
     * @return bool|string
     */
    private function generateSources()
    {
        // Necesito generar las urls, y luego ya las filas con los tamaños
        $output = " srcset='";
        $thumbs = array_reverse($this->thumbs);
        foreach ($thumbs as $thumb) {
            $thumbUrl = str_replace($this->thumbsFolderPath, $this->thumbsFolderUrl, $thumb['path']);
            $output .= $thumbUrl . " {$thumb['width']}w,\n";
        }
        $output = substr($output, 0, -2);
        $output .= "' ";
        return $output;
    }
}
