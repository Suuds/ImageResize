<?php
/*
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::
::	GIFDecoder Version 2.0 by L�szl� Zsidi
::
::	Created at 2007. 02. 01. '07.47.AM'
::
::	Updated at 2009. 06. 23. '06.00.AM'
::
:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
*/

Class GIFDecoder {
    private $GIF_TransparentR =  -1;
	private $GIF_TransparentG =  -1;
	private $GIF_TransparentB =  -1;
	private $GIF_TransparentI =   0;

	private $GIF_buffer = Array ( );
	private $GIF_arrays = Array ( );
	private $GIF_delays = Array ( );
	private $GIF_dispos = Array ( );
	private $GIF_stream = "";
	private $GIF_string = "";
	private $GIF_bfseek =  0;
	private $GIF_anloop =  0;

	private $GIF_screen = Array ( );
	private $GIF_global = Array ( );
	private $GIF_sorted;
	private $GIF_colorS;
	private $GIF_colorC;
	private $GIF_colorF;
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFDecoder ( $GIF_pointer )
	::
	*/
       public function __construct ( $GIF_pointer ) {
		$this->GIF_stream = $GIF_pointer;

		$this->GIFGetByte ( 6 );
		$this->GIFGetByte ( 7 );

		$this->GIF_screen = $this->GIF_buffer;
		$this->GIF_colorF = $this->GIF_buffer [ 4 ] & 0x80 ? 1 : 0;
		$this->GIF_sorted = $this->GIF_buffer [ 4 ] & 0x08 ? 1 : 0;
		$this->GIF_colorC = $this->GIF_buffer [ 4 ] & 0x07;
		$this->GIF_colorS = 2 << $this->GIF_colorC;

		if ( $this->GIF_colorF == 1 ) {
			$this->GIFGetByte ( 3 * $this->GIF_colorS );
			$this->GIF_global = $this->GIF_buffer;
		}
		for ( $cycle = 1; $cycle; ) {
			if ( $this->GIFGetByte ( 1 ) ) {
				switch ( $this->GIF_buffer [ 0 ] ) {
					case 0x21:
						$this->GIFReadExtensions ( );
						break;
					case 0x2C:
						$this->GIFReadDescriptor ( );
						break;
					case 0x3B:
						$cycle = 0;
						break;
				}
			}
			else {
				$cycle = 0;
			}
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFReadExtension ( )
	::
	*/
	private function GIFReadExtensions ( ) {
		$this->GIFGetByte ( 1 );
		if ( $this->GIF_buffer [ 0 ] == 0xff ) {
			for ( ; ; ) {
				$this->GIFGetByte ( 1 );
				if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
					break;
				}
				$this->GIFGetByte ( $u );
				if ( $u == 0x03 ) {
					$this->GIF_anloop = ( $this->GIF_buffer [ 1 ] | $this->GIF_buffer [ 2 ] << 8 );
				}
			}
		}
		else {
			for ( ; ; ) {
				$this->GIFGetByte ( 1 );
				if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
					break;
				}
				$this->GIFGetByte ( $u );
				if ( $u == 0x04 ) {
					if ( $this->GIF_buffer [ 4 ] & 0x80 ) {
						$this->GIF_dispos [ ] = ( $this->GIF_buffer [ 0 ] >> 2 ) - 1;
					}
					else {
						$this->GIF_dispos [ ] = ( $this->GIF_buffer [ 0 ] >> 2 ) - 0;
					}
					$this->GIF_delays [ ] = ( $this->GIF_buffer [ 1 ] | $this->GIF_buffer [ 2 ] << 8 );
					if ( $this->GIF_buffer [ 3 ] ) {
						$this->GIF_TransparentI = $this->GIF_buffer [ 3 ];
					}
				}
			}
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFReadExtension ( )
	::
	*/
	private function GIFReadDescriptor ( ) {
		$GIF_screen	= Array ( );

		$this->GIFGetByte ( 9 );
		$GIF_screen = $this->GIF_buffer;
		$GIF_colorF = $this->GIF_buffer [ 8 ] & 0x80 ? 1 : 0;
		if ( $GIF_colorF ) {
			$GIF_code = $this->GIF_buffer [ 8 ] & 0x07;
			$GIF_sort = $this->GIF_buffer [ 8 ] & 0x20 ? 1 : 0;
		}
		else {
			$GIF_code = $this->GIF_colorC;
			$GIF_sort = $this->GIF_sorted;
		}
		$GIF_size = 2 << $GIF_code;
		$this->GIF_screen [ 4 ] &= 0x70;
		$this->GIF_screen [ 4 ] |= 0x80;
		$this->GIF_screen [ 4 ] |= $GIF_code;
		if ( $GIF_sort ) {
			$this->GIF_screen [ 4 ] |= 0x08;
		}
		/*
		 *
		 * GIF Data Begin
		 *
		 */
		if ( $this->GIF_TransparentI ) {
			$this->GIF_string = "GIF89a";
		}
		else {
			$this->GIF_string = "GIF87a";
		}
		$this->GIFPutByte ( $this->GIF_screen );
		if ( $GIF_colorF == 1 ) {
			$this->GIFGetByte ( 3 * $GIF_size );
			if ( $this->GIF_TransparentI ) {
				$this->GIF_TransparentR = $this->GIF_buffer [ 3 * $this->GIF_TransparentI + 0 ];
				$this->GIF_TransparentG = $this->GIF_buffer [ 3 * $this->GIF_TransparentI + 1 ];
				$this->GIF_TransparentB = $this->GIF_buffer [ 3 * $this->GIF_TransparentI + 2 ];
			}
			$this->GIFPutByte ( $this->GIF_buffer );
		}
		else {
			if ( $this->GIF_TransparentI ) {
				$this->GIF_TransparentR = $this->GIF_global [ 3 * $this->GIF_TransparentI + 0 ];
				$this->GIF_TransparentG = $this->GIF_global [ 3 * $this->GIF_TransparentI + 1 ];
				$this->GIF_TransparentB = $this->GIF_global [ 3 * $this->GIF_TransparentI + 2 ];
			}
			$this->GIFPutByte ( $this->GIF_global );
		}
		if ( $this->GIF_TransparentI ) {
			$this->GIF_string .= "!\xF9\x04\x1\x0\x0". chr ( $this->GIF_TransparentI ) . "\x0";
		}
		$this->GIF_string .= chr ( 0x2C );
		$GIF_screen [ 8 ] &= 0x40;
		$this->GIFPutByte ( $GIF_screen );
		$this->GIFGetByte ( 1 );
		$this->GIFPutByte ( $this->GIF_buffer );
		for ( ; ; ) {
			$this->GIFGetByte ( 1 );
			$this->GIFPutByte ( $this->GIF_buffer );
			if ( ( $u = $this->GIF_buffer [ 0 ] ) == 0x00 ) {
				break;
			}
                        $this->GIFGetByte ( $u );
			$this->GIFPutByte ( $this->GIF_buffer );
		}
		$this->GIF_string .= chr ( 0x3B );
		/*
		 *
		 * GIF Data End
		 *
		 */
		$this->GIF_arrays [ ] = $this->GIF_string;
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetByte ( $len )
	::
	*/
	private function GIFGetByte ( $len ) {
		$this->GIF_buffer = Array ( );

		for ( $i = 0; $i < $len; $i++ ) {
			if ( $this->GIF_bfseek > strlen ( $this->GIF_stream ) ) {
				return 0;
			}
			$this->GIF_buffer [ ] = ord ( $this->GIF_stream { $this->GIF_bfseek++ } );
		}
		return 1;
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFPutByte ( $bytes )
	::
	*/
	private function GIFPutByte ( $bytes ) {
		foreach ( $bytes as $byte ) {
			$this -> GIF_string .= chr ( $byte );
		}
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	PUBLIC FUNCTIONS
	::
	::
	::	GIFGetFrames ( )
	::
	*/
       public function GIFGetFrames ( ) {
		return ( $this->GIF_arrays );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetDelays ( )
	::
	*/
	public function GIFGetDelays ( ) {
		return ( $this->GIF_delays );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetLoop ( )
	::
	*/
	public function GIFGetLoop ( ) {
		return ( $this->GIF_anloop );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetDisposal ( )
	::
	*/
	public function GIFGetDisposal ( ) {
		return ( $this->GIF_dispos );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetTransparentR ( )
	::
	*/
	public function GIFGetTransparentR ( ) {
		return ( $this->GIF_TransparentR );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetTransparentG ( )
	::
	*/
	public function GIFGetTransparentG ( ) {
		return ( $this->GIF_TransparentG );
	}
	/*
	:::::::::::::::::::::::::::::::::::::::::::::::::::
	::
	::	GIFGetTransparentB ( )
	::
	*/
	public function GIFGetTransparentB ( ) {
		return ( $this->GIF_TransparentB );
	}
}
?>
