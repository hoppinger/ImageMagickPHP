<?php

/*
 * This file is part of the OrbitaleImageMagickPHP package.
 *
 * (c) Alexandre Rock Ancelet <alex@orbitale.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Orbitale\Component\ImageMagick\ReferenceClasses;

/**
 * Represents an ImageMagick geometry parameter.
 * Each value is optional so a big regex is used to parse it.
 *
 * How to build the monstruous regexp? Check the gist link.
 *
 * @link https://gist.github.com/Pierstoval/eac8d182d2c51c93202f
 * @link http://www.imagemagick.org/script/command-line-processing.php#geometry
 */
class Geometry
{
    const REGEX_VALIDATE = '~(?<size>(?<w>(?:\d*(?:\.\d+)?)?%?)?(?:(?<whseparator>x)(?<h>(?:\d*(?:\.\d+)?)?%?))?)(?<aspect>[!><@^])?(?<offset>(?<x>[+-]\d*(?:\.\d+)?)?(?<y>[+-]\d*(?:\.\d+)?)?)~';

    const RATIO_NONE    = null;
    const RATIO_MIN     = '^';
    const RATIO_IGNORE  = '!';
    const RATIO_SHRINK  = '>';
    const RATIO_ENLARGE = '<';

    private static $validRatios = array(self::RATIO_ENLARGE, self::RATIO_IGNORE, self::RATIO_MIN, self::RATIO_SHRINK);

    /**
     * @var string
     */
    private $value = '';

    /**
     * @param int    $width Can be both
     * @param int    $height
     * @param int    $x
     * @param int    $y
     * @param string $aspectRatio
     *
     * @return string
     */
    public static function createFromParameters($width = null, $height = null, $x = null, $y = null, $aspectRatio = self::RATIO_NONE)
    {
        $geometry = $width;

        // If we have a height
        // Or if we have width, no height and an offset
        // If width is 100, it will result in 100x{offset}
        // else, 100{offset} is incorrect
        if (null !== $height || ($width && !$height && (null !== $x || null !== $y))) {
            $geometry .= 'x';
        }

        if (null !== $height) {
            $geometry .= $height;
        }

        if ($aspectRatio && !in_array($aspectRatio, self::$validRatios, true)) {
            throw new \InvalidArgumentException(sprintf(
                "Invalid aspect ratio value to generate geometry, \"%s\" given.\nAvailable: %s",
                $aspectRatio, implode(', ', self::$validRatios)
            ));
        }
        $geometry .= $aspectRatio;

        if (null !== $x) {
            $geometry .= ($x >= 0 ? '+' : '-').abs($x);
            if (null !== $y) {
                $geometry .= ($y >= 0 ? '+' : '-').abs($y);
            }
        } elseif (null !== $y) {
            if (null !== $y) {
                $geometry .= '+0'.($y >= 0 ? '+' : '-').abs($y);
            }
        }

        return $geometry;
    }

    public function __construct($width = null, $height = null, $x = null, $y = null, $aspectRatio = self::RATIO_NONE)
    {
        $args = func_get_args();

        $geometry = $width;

        if (count(array_map(null, $args)) > 1) {
            $geometry = call_user_func_array(array($this, 'createFromParameters'), $args);
        }

        $this->value = $geometry;
    }

    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * @throws \InvalidArgumentException
     * @return string
     */
    public function validate()
    {
        $errors = array();

        if (!preg_match(static::REGEX_VALIDATE, $this->value, $matches)) {
            $errors[] = 'Invalid regexp.';
        }

        $w = isset($matches['w']) && '' !== $matches['w'] ? $matches['w'] : null;
        $h = isset($matches['h']) && '' !== $matches['h'] ? $matches['h'] : null;
        $x = isset($matches['x']) && '' !== $matches['x'] ? $matches['x'] : null;
        $y = isset($matches['y']) && '' !== $matches['y'] ? $matches['y'] : null;
        $offset = isset($matches['offset']) && '' !== $matches['offset'] ? $matches['offset'] : null;
        $whseparator = $matches['whseparator'];
        $aspect = $matches['aspect'];

        // The next checks will perform post-regexp matching that is impossible with preg_match natively

        if ('0' === $w || '0' === $h) {
            $errors[] = 'Cannot specify zero width nor height.';
        }
        if ($aspect && !$w && !$h) {
            $errors[] = 'Aspect can be used only with width and/or height.';
        }

        if ($w && !$h && ($x || $y) && !$whseparator) {
            $errors[] = 'When using offsets and only width, you must specify the "x" separator like this: '.$w.'x'.$offset;
        }

        if (count($errors)) {
            throw new \InvalidArgumentException(sprintf(
                "The specified geometry (%s) is invalid.\n%s\n".
                "Please refer to ImageMagick command line documentation about geometry:\n%s\n",
                $this->value,
                implode("\n", $errors),
                'http://www.imagemagick.org/script/command-line-processing.php#geometry'
            ));
        }

        $this->value = trim($this->value);

        return $this->value;
    }
}
