<?php

require_once (__DIR__ . '/GIFDecoder.php');
require_once (__DIR__ . '/GIFEncoder.php');

class ImageResizer {
    public $path_file;
    public $name_file;
    /** @var array $frames кадры аннимации */
    private $frames;

    /** @var bool $gifDeconder флаг режима работы с аннимацией */
    private $gifDecoder = false;      //тип оброботки true - класс Decode class

    /** @var string $mode режим обработки */
    private $mode;

    /** @var string $ratio [пропорция|соотношение] */
    private $ratio = '';

    /*
     * resize - изменить размер 
     * ration_resize - изменить размер в соответствии с выбранным соотношением
     * crop_resize   - обрезать и изменить размер
     * crop          - обрезать   
     */

    /** @var array $modes режими обработки изображения */
    private $modes = array(
        'resize', 'ratio_resize', 'crop_resize', 'crop'
    );

    /** @var array $cropModes режимы обрезания изображения */
    private $cropModes = array(
        'top_left', 'top_center', 'top_right',
        'center_left', 'center', 'center_right',
        'bottom_left', 'bottom_center', 'bottom_right'
    );

    /** @var string режим обрезания изображения */
    private $cropMode;

    /** @var int $sourceWidth исходная ширина */
    private $sourceWidth;

    /** @var int $sourceHeight исходная высота */
    private $sourceHeight;

    /** @var string $sourceType соответствующий MIME-тип исходного изображения */
    public $sourceType;

    /** @var #recource $sourceImg идентификатор изображения, представляющего изображение */
    public $sourceImg;

    /** @var string $sourceImgPath путь к исходному файлу (URL) */
    private $sourceImgPath;

    /** @var int $newWidth новая ширина изображения */
    private $newWidth;

    /** @var int $newHeight новая высота изображения */
    private $newHeight;

    /** @var #recource $newImage обработанное изображения */
    private $newImage;

    /** @var array массив допустимых расширений изображения */
    private $imageTypes = array(
        'image/gif' => 'gif',
        'image/jpeg' => 'jpeg',
        'image/jpg' => 'jpeg',
        'image/png' => 'png',
        'image/bmp' => 'bmp'
    );

    // thumb -> thumbnails иконки изображений

    /** @var string $thumbsPath путь сохранения иконок изображений */
    private $thumbsPath = './files/images/thumbs/';

    /** @var sring $thumbName имя для иконки изображения */
    private $thumbName;

    /** @var string $thumbSrc путь к созданной иконке изображения */
    private $thumbSrc;

    /** @var string $thumbPath абсолютный путь к иконке изображения */
    private $thumbPath;

    /** @var string $redraw */
    private $redraw;

    /** @var $cropWidth - ширина до которой надо обрезать */
    private $cropWidth;

    /** @var $cropHeight - высота до которой надо обрезать */
    private $cropHeight;

    /**
     * Инициализировать путь к картинке, в случае если картинка не 
     * найдена вывести сообщение и прекратить выполнения сценария
     * 
     * @param string $sourceImgPath путь к файлу
     */
    public function __construct($sourceImgPath) {
        if (!file_exists($sourceImgPath))
            die('Изображение ' . $sourceImgPath . ' не найдено!');
        $this->sourceImgPath = $sourceImgPath;
        $this->checkImagick();
    }

    /**
     * Получить информацию об исходном изображении
     *    {@link $courceWidth}  - ширина
     *    {@link $courceHeight} - высота
     *    {@link $courceType}   - соответствующий MIME-тип изображения
     *  Используется в {@link setSize()}
     */
    private function getImageInfo() {
        $imageInfo = getimagesize($this->sourceImgPath);
        $this->sourceWidth = $imageInfo[0];
        $this->sourceHeight = $imageInfo[1];

        if ($this->checkImageTypeSupport($imageInfo['mime']))
            $this->sourceType = $this->imageTypes[$imageInfo['mime']];
    }

    /**
     * Проверяет, присутствует ли в массиве {@link $imageTypes}  полученый MIME тип изображения
     * используется в {@link getImageInfo()}
     * 
     * @param string $imageType соответствующий MIME-тип изображения
     * @return boolean true в случае успеха, иначе вывести сообщение и прекратить выполнения скрипта
     */
    private function checkImageTypeSupport($imageType) {
        if (array_key_exists($imageType, $this->imageTypes))
            return true;

        die('Тип изображения ' . $imageType . ' не поддерживается.');
    }

    /**
     *  Создает исходное изображение из URL или файла
     * 
     *  imagecreatefrom.... -  возвращает идентификатор изображения,
     *                         представляющего изображение полученное
     *                         из файла или URL
     *  Задействованные свойства:
     *    1. {@link $sourceImgPath}
     *    2. {@link $sourceType}
     *    3. {@link $sourceImg}
     */
    private function createImageFromSource() {
        switch ($this->sourceType) {
            case "jpg":
            case "jpeg":
                $sourceImg = imagecreatefromjpeg($this->sourceImgPath);
                break;

            case "gif":
                $sourceImg = imagecreatefromgif($this->sourceImgPath);
                break;

            case "png":
                $sourceImg = imagecreatefrompng($this->sourceImgPath);
                break;

            case "bmp":
                $sourceImg = imagecreatefromwbmp($this->sourceImgPath);
                break;
        }
        if ($sourceImg)
            $this->sourceImg = $sourceImg;
    }

    /**
     * Проверяет является ли изображение анимированым
     * Задействованные свойства:
     *    1. {@link $sourceImgPath}
     * 
     * @return boolean результат сравнения, true - анимировано, false - не анимировано
     */
    public function isAnimated() {
        $lsd_offset = 6;    // logical screen descriptor 
        $gct_offset = 13;   // global color table 
        $chunk_size = 2048; // 

        $fd = fopen($this->sourceImgPath, 'rb');
        $buff = fread($fd, $chunk_size);
        fclose($fd);

        $packed = ord($buff[$lsd_offset + 4]);
        $gct_flag = ($packed >> 7) & 1;
        $gct_size = $packed & 7;

        $gct_length = 1 << ($gct_size + 1);
        $data_offset = $gct_offset + ($gct_flag ? 3 * $gct_length : 0);

        while ($data_offset < strlen($buff)) {
            if ((ord($buff[$data_offset]) == 0x21) && (ord($buff[$data_offset + 1]) == 0xf9)) {
                // we hit a Graphic control extension 
                $delay_time = ord($buff[$data_offset + 5]) << 8 | ord($buff[$data_offset + 4]);
                if ($delay_time > 0) {
                    return true;
                } else {
                    return false;
                }
            } elseif ((ord($buff[$data_offset]) == 0x21) && (ord($buff[$data_offset + 1]) == 0xff)) {
                // we hit an application extension 
                $app_name = substr($buff, $data_offset + 3, 8);
                $app_bytes = substr($buff, $data_offset + 11, 3);
                $app_data = array();
                $data_offset += 14;
                do {
                    $size = ord($buff[$data_offset]);
                    $app_data[] = substr($buff, $data_offset + 1, $size);
                    $data_offset += $size + 1;
                } while (ord($buff[$data_offset]) != 0);
                $data_offset += 1;

                // found Netscape looping extension. GIF is animated. 
                if (('NETSCAPE' == $app_name) && ('2.0' == $app_bytes) && (3 == strlen($app_data[0])) && (1 == ord($app_data[0][0]))) {
                    return true;
                }
            } elseif ((ord($buff[$data_offset]) == 0x21) && (ord($buff[$data_offset + 1]) == 0xfe)) {
                // we hit a comment extension 
                $data_offset += 2;
                do {
                    $size = ord($buff[$data_offset]);
                    $data_offset += $size + 1;
                } while (ord($buff[$data_offset]) != 0);
                $data_offset += 1;
            } elseif (ord($buff[$data_offset]) == 0x2c) {
                // we hit an actual image 
                return false;
            } else {
                return false;
            }
        }
    }

    /**
     * Устанавливает режим  обработки изображения
     * Задействованные свойства:
     *    1. {@link $modes}
     *    2. {@link $mode}
     * 
     * @param string $mode режим обработки изображения
     * @return \ImageResizer класс
     */
    public function setMode($mode) {
        if (!in_array($mode, $this->modes))
            die('Недопустимый режим ' . $mode);
        $this->mode = $mode;
        return $this;
    }

    /**
     * Установить размер
     * 
     * @param int $width  ширина
     * @param int $height высота
     * @param string $ratio [пропорция|соотношение]
     * @return \ImageResizer класс
     */
    public function setSize($width, $height, $ratio = 'width') {

        $this->getImageInfo(); // иницилизирует свойства 
        // 1. $sourceWidth  - исходная ширина
        // 2. $sourceHeight - исходная высота
        // Проверяем ширину и высоту на корректность
        if ($width <= 0 && $height <= 0)
            die('Неверно указаны размеры');

        // Инициализируем свойсва новых размеров изображения
        $this->newWidth = $width;
        $this->newHeight = $height;

        // Если выбран режим crop_resize или crop иницилизировать свойства
        if ($this->mode == 'crop_resize' || $this->mode == 'crop') {
            $this->cropWidth = $width;
            $this->cropHeight = $height;
        }

        // Если выбран режим ratio_resize или crop_resize иницилизировать свойство $ratio
        if ($this->mode == 'ratio_resize' || $this->mode == 'crop_resize') {
            $this->setRatio($ratio);

            // изменяем размер изображения в соответстивии с $ratio [пропорция|соотношение]
            switch ($ratio) {

                case "width":
                    // newH = (oldH/oldW)* newW 
                    $this->newWidth = $width;
                    $this->newHeight = round(($this->sourceHeight / $this->sourceWidth) * $this->newWidth);

                    if ($this->newHeight < $this->cropHeight) {
                        $ratio = 'height';
                        $this->setSize($width, $height, $ratio);
                    }
                    break;

                case "height":
                    // newW = (oldW/oldH)* newH 
                    $this->newHeight = $height;
                    $this->newWidth = round(($this->sourceWidth / $this->sourceHeight) * $this->newHeight);

                    if ($this->newWidth < $this->cropWidth) {
                        $ratio = 'width';
                        $this->setSize($width, $height, $ratio);
                    }
                    
                    break;
            }
        }

        $this->setThumbName();
        return $this;
    }

    /**
     * Инициализирует свойства:
     *    1. {@link $thumbName} имя сохраняемого изображения
     *    2. {@link $thumbSrc}  путь сохранения изображения
     *    3. {@link $thumbPath} абсолютный путь к сохранному изображению
     */
    private function setThumbName() {
        $imgName = pathinfo($this->sourceImgPath);
        if (array_key_exists('extension', $imgName))
            $imgExt = '.' . $imgName['extension'];
        else
            $imgExt = '';

        // Если свойство $ratio не пусто и $mode(режим обработки) не равен обрезке
        // добавить $ration к $mode
        if ($this->ratio !== '' && $this->mode !== 'crop')
        // crop_resize_width
            $mode = $this->mode . '_' . $this->ratio;
        else // иначе просто режим обработки
        // crop   
            $mode = $this->mode;

        $width = $this->newWidth;
        $height = $this->newHeight;

        // Если есть обрезка - режим обработки изображения + режим обрезки
        if ($this->mode == 'crop_resize' || $this->mode == 'crop') {
            // crop_resize_width_center
            $mode = $mode . '_' . $this->cropModes[$this->cropMode];
            
            $width = $this->cropWidth;
            $height = $this->cropHeight;
        }
        // имя нового файла image_crop_resize_width_center_100_50.jpg
        $this->thumbName =$this->name_file;
        //"./files/images/thumbs/"
        // путь сохранения './files/images/thumbs/' + image_crop_resize_center_100_50.jpg
        $this->thumbSrc = $this->path_file . $this->thumbName; // используется при сохранении saveImage
        // абсолютный путь к изображению $_SERVER['DOCUMENT_ROOT'] + /files/images/thumbs/ + image_crop_resize_center_100_50.jpg
        $this->thumbPath = $_SERVER['DOCUMENT_ROOT'] . substr($this->thumbSrc, 1); // используются в imagick
    }

    /**
     * Устанавливает [пропорцию|соотношению] -> [width|height], 
     * иначе выводит сообщение об ошибке и завершает сценарий
     * 
     * @param string $ratio [пропорция|соотношение]
     * @return \ImageResizer класс
     */
    public function setRatio($ratio) {
        if ($ratio !== 'width' && $ratio !== 'height')
            die('Неверно указан параметр пропорций');

        $this->ratio = $ratio;

        return $this;
    }

    /**
     * Получить абсолютный путь к изображению
     * будет искать начиная с корня
     * 
     * @return string {$link $thumbSrc}
     */
    public function getThumbSrc() {
        if (!$this->checkThumbExists() || $this->redraw)
            $this->processImage();

        return substr($this->thumbSrc, 1);
    }

    /**
     * Получить относительный путь к изображению
     * будет искать изображения относительно текущего местоположения этого класса
     * 
     * @return string {@link thumbSrc} 
     */
    public function getThumbPath() {
        if (!$this->checkThumbExists() || $this->redraw)
            $this->processImage();
        return $this->thumbSrc;
    }

    /**
     * Функция предназначена для обработки, в зависимости от выбранного режима обработки, и сохранения изображения 
     * 
     * @return boolean true в случае удачи, иначе вывести ошибку и прекратить выполнение скрипта
     */
    private function processImage() {
        $this->createImageFromSource();

        switch ($this->mode) {
            case "resize":
            case "ratio_resize":
                $this->resizeSourceImage();
                break;

            case "crop_resize":
                $this->cropResizeSourceImage();
                break;

            case "crop":
                $this->cropSourceImage();
                break;
        }

        if (!$this->saveImage())
            die('Не удалось сохранить изображение');

        return true;
    }

    /**
     * Проверяет существует ли изображение по указаному URL
     * 
     * @return bool true - данной изображение уже существует, иначе false
     */
    private function checkThumbExists() {
        return file_exists($this->thumbSrc);
    }

    /**
     * Ресайз изображения
     */
    private function resizeSourceImage() {
        $tool = 'standart';

        if ($this->sourceType == 'gif') {
            if ($this->isAnimated())
                $tool = 'imagick';
        }
        
        if ($tool == 'standart') {
            $newImage = imagecreatetruecolor($this->newWidth, $this->newHeight);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            imagecopyresampled($newImage, $this->sourceImg, 0, 0, 0, 0, $this->newWidth, $this->newHeight, $this->sourceWidth, $this->sourceHeight);
            $this->newImage = $newImage;
        } else {

            if ($this->gifDecoder === true) {
                $this->GifResize();     //gif библиотека
            } else {
                // Ограничить количество создаваемых потоков в ImageMagick
                putenv("MAGICK_THREAD_LIMIT=1");
                $imageMagickPath = config::self()->getSetting('system', 'imagick_path');
                $exec_string = $imageMagickPath . ' ' . $this->sourceImgPath . ' -coalesce -resize ' . $this->newWidth . 'x' . $this->newHeight . ' ' . $this->thumbPath;
                 // выполняет внешнюю программу, в $answer хранится код возврата выполняемой команды
                system($exec_string, $answer);
            }
        }
    }
    
    /**
     * Ресайз и обрезка изображений
     */
    private function cropResizeSourceImage() {
        $cropPosition = $this->getCropPosition();
        /* resize image */
        $this->resizeSourceImage();
        $tool = 'standart';

        if ($this->sourceType == 'gif') {
            if ($this->isAnimated())
                $tool = 'imagick';
        }

        if ($tool == 'standart') {
            $resizedImage = $this->newImage;
            $croppedImage = imagecreatetruecolor($this->cropWidth, $this->cropHeight);
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            imagecopy($croppedImage, $resizedImage, 0, 0, $cropPosition[0], $cropPosition[1], $this->cropWidth, $this->cropHeight);
            $this->newImage = $croppedImage;
        } else {
            if ($this->gifDecoder === true) {
                $this->GifCropResize($cropPosition);   // Ресайз и обрезка
            } else {
                putenv("MAGICK_THREAD_LIMIT=1");
                $imageMagickPath = config::self()->getSetting('system', 'imagick_path');
                $exec_string = $imageMagickPath . ' ' . $this->thumbPath . ' -coalesce -crop ' . $this->cropWidth . 'x' . $this->cropHeight . '+' . $cropPosition[0] . '+' . $cropPosition[1] . ' -trim +repage ' . $this->thumbPath;
                system($exec_string, $answer);
            }
        }
    }
    
    /**
     *  Обрезка изображений
     */
    private function cropSourceImage() {
        // получить позицию для обрезки
        $cropPosition = $this->getCropPosition();

        // устанавливаем по умолчанию способ обрезки
        $tool = 'standart';

        // проверка что изображение в gif формате
        // в случаем успеха проверка что изображение анимировано и что расширение imagick загружено
        // в случае успеха установить установить способ обрезки с помощью imagic
        if ($this->sourceType == 'gif') {
            if ($this->isAnimated())
                $tool = 'imagick';
        }

        if ($tool == 'standart') {
            // идентификатор изображения, представляющий черное изображение заданного размера.
            $croppedImage = imagecreatetruecolor($this->cropWidth, $this->cropHeight);
            // установить режим без сопряжения цветов
            imagealphablending($croppedImage, false);
            // Для использования функции необходимо отключить альфа сопряжение с помощью imagealphablending
            // установка флага сохранения всей информации альфа компонента 
            // чтобы показать прозрачность того или иного объекта, используются текстуры с альфа-каналом.     
            // RGBA Red+Green+Blue+Alpha
            imagesavealpha($croppedImage, true);
            imagecopy($croppedImage, $this->sourceImg, 0, 0, $cropPosition[0], $cropPosition[1], $this->cropWidth, $this->cropHeight);
            $this->newImage = $croppedImage;
        } else {
            if ($this->gifDecoder === true) {
                $this->GifCrop($cropPosition);     // обрезка Gif
            } else {
                putenv("MAGICK_THREAD_LIMIT=1");
                $imageMagickPath = config::self()->getSetting('system', 'imagick_path');
                $exec_string = $imageMagickPath . ' ' . $this->sourceImgPath . ' -coalesce -crop ' . $this->cropWidth . 'x' . $this->cropHeight . '+' . $cropPosition[0] . '+' . $cropPosition[1] . ' +repage ' . $this->thumbPath;
                system($exec_string, $answer);
            }
        }
    }

    /**
     * Получает координаты обрезки
     * 
     * @return array координаты для обрезки
     */
    private function getCropPosition() {
        if ($this->mode == 'crop') {
            $width = $this->sourceWidth;
            $height = $this->sourceHeight;
        } else {
            $width = $this->newWidth;
            $height = $this->newHeight;
        }

        $imageCenter = array(
            'x' => $width /  2,
            'y' => $height / 2
        );

        switch ($this->cropMode) {
            // top_left
            case 0:
                return array(0, 0);
                break;
            // top_center
            case 1:
                return array($imageCenter['x'] - ($this->cropWidth / 2), 0);
                break;
            // top_right
            case 2:
                return array($width - $this->cropWidth, 0);
                break;
            // center_left
            case 3:
                return array(0, $imageCenter['y'] - ($this->cropHeight / 2));
                break;
            // center
            case 4:
                return array($imageCenter['x'] - ($this->cropWidth / 2), $imageCenter['y'] - ($this->cropHeight / 2));
                break;
            // center_right
            case 5:
                return array($width - $this->cropWidth, $imageCenter['y'] - ($this->cropHeight / 2));
                break;
            // botton_left
            case 6:
                return array(0, $height - $this->cropHeight);
                break;
            // botton_center
            case 7:
                return array($imageCenter['x'] - ($this->cropWidth / 2), $height - $this->cropHeight);
                break;
            // botton_right
            case 8:
                return array($width - $this->cropWidth, $height - $this->cropHeight);
                break;
        }
    }

    /**
     * Сохраняет изображение
     * 
     * @return boolean true в случае успеха, иначе false
     */
    private function saveImage() {
        if ($this->checkThumbExists() && $this->redraw && !$this->sourceType == 'gif')
            unlink($this->thumbSrc);

        switch ($this->sourceType) {
            case "jpeg":
            case "jpg":
                imageinterlace($this->newImage, true);
                return imagejpeg($this->newImage, $this->thumbSrc, 100);
                break;

            case "png":
                imageinterlace($this->newImage, true);
                return imagepng($this->newImage, $this->thumbSrc);
                break;
            
            case "gif":
                if (!$this->isAnimated()) {
                    imageinterlace($this->newImage, true);
                    return imagegif($this->newImage, $this->thumbSrc);
                } elseif ($this->isAnimated()) {
                    return file_put_contents($this->thumbSrc, $this->newImage);
                } else {
                    return file_exists($this->thumbPath);
                }
                break;
                
            case "bmp":
                imageinterlace($this->newImage, true);
                return imagewbmp($this->newImage, $this->thumbSrc);
                break;
        }
    }

    /**
     * Установить режим обрезания
     * 
     * @param int $mode № режима обрезания изображения
     * @return \ImageResizer класс
     */
    public function setCropMode($mode) {
        $mode = intval($mode);
        if ($mode < 0 || $mode > 8)
            die('Не правильный режим обрезания изображения');
        $this->cropMode = $mode;
        return $this;
    }

    // ????
    public function redrawThumb($redraw) {
        $this->redraw = $redraw;
        return $this;
    }

    /**
     * Проверка загруженo ли встроенное php-расширение Imagick
     * 
     * @return boolean true в случае успеха, иначе false
     */
    public function checkImagick() {
        if (extension_loaded('imagick')) {
            return true;
        } else {
          // $imagick_path = config::self()->getSetting('system', 'imagick_path');
            if ($imagick_path && !empty($imagick_path))
                return true;
        }
        $this->gifDecoder = true;
    }

    /**
     * Разобрать изображение и получить массив кадров
     * 
     * @return array $frames массив кадров
     */
    private function GetFrames($file) {
        $gifDecoder = new GIFDecoder($file, filesize($this->sourceImgPath));
        $this->frames = $gifDecoder->GIFGetFrames();
    }

    /**
     * Получить картинки и возвратить собранное Gif изображение
     * 
     * @return string GIF 
     */
    private function GetGif() {
        $gif = new GIFEncoder( $this->frames, 0, 0, 2, 255, 255, 255, "bin" );
        return $gif->GetAnimation();
    }

    /**
     * Ресайз изображения
     */
    private function GifResize() {
        // разбиваем gif
        $this->GetFrames(file_get_contents($this->sourceImgPath));
        //цикл перебора кадров
        foreach ($this->frames as $key => $frame) {
            $newImage = imagecreatetruecolor($this->newWidth, $this->newHeight);
            imagecopyresampled($newImage, imagecreatefromstring($frame), 0, 0, 0, 0, $this->newWidth, $this->newHeight, $this->sourceWidth, $this->sourceHeight);
            
            $temfile = tempnam('/tmp', '');
            imagegif($newImage, $temfile);
            $this->frames[$key] = file_get_contents($temfile);
            unlink($temfile);
            imagedestroy($newImage);
        }
        // собираем gif
        $this->newImage = $this->GetGif();
    }

    /**
     * Включить класс Decode & Encode
     * 
     * @param mixed $mode флаг включения
     * @return  $this->gifDecoder=true;  
     */
    public function setModeGif($mode) {
        if ($mode == 'true' || $mode === true)
            $this->gifDecoder = true;
    }

    /**
     * Ресайз и обрезка Gif изображения
     * 
     * @param array $cropPosition
     */
    private function GifCropResize($cropPosition) {
        // разбиваем gif
        $this->GetFrames($this->newImage);
        // цикл перебора кадров
        foreach ($this->frames as $key => $frame) {
            $croppedImage = imagecreatetruecolor($this->cropWidth, $this->cropHeight);
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            imagecopy($croppedImage, imagecreatefromstring($frame), 0, 0, $cropPosition[0], $cropPosition[1], $this->cropWidth, $this->cropHeight);
            
            $temfile = tempnam('/tmp', '');
            imagegif($croppedImage, $temfile);
            $this->frames[$key] = file_get_contents($temfile);
            unlink($temfile);
            imagedestroy($croppedImage);
        }
        // собираем gif
        $this->newImage = $this->GetGif();
    }

    /**
     * Обрезка Gif изображения
     * 
     * @param array $cropPosition позиция обрезки изображения
     */
    private function GifCrop($cropPosition) {
        // разбиваем gif
        $this->GetFrames(file_get_contents($this->sourceImgPath));
        // цикл перебора кадров
        foreach ($this->frames as $key => $frame) {
            // идентификатор изображения, представляющий черное изображение заданного размера.
            $croppedImage = imagecreatetruecolor($this->cropWidth, $this->cropHeight);
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            imagecopy($croppedImage, imagecreatefromstring($frame), 0, 0, $cropPosition[0], $cropPosition[1], $this->cropWidth, $this->cropHeight);

            $temfile = tempnam('/tmp', '');
            imagegif($croppedImage, $temfile);
            $this->frames[$key] = file_get_contents($temfile);
            unlink($temfile);
            imagedestroy($croppedImage);
        }
        // собираем gif
        $this->newImage = $this->GetGif();
    }

}