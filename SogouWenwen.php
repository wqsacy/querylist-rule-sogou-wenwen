<?php

	namespace QL\Ext;

	use QL\Contracts\PluginContract;
	use QL\QueryList;

	/**
	 * QueryList Rule sogou
	 * Created by Malcolm.
	 * Date: 2021/4/25  15:59
	 */
	class SogouWenwen implements PluginContract
	{

		protected $ql;
		protected $keyword;
		protected $pageNumber = 10;
		protected $httpOpt = [
			'headers' => [
				'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36' ,
				'Accept-Encoding' => 'gzip, deflate, br' ,
			]
		];
		const API = 'https://www.sogou.com/sogou';
		const RULES = [
			'title' => [ 'h3>a' , 'text' ] ,
			'link'  => [ 'h3>a' , 'href' ]
		];
		const RANGE = '.results>div';

		public function __construct ( QueryList $ql , $pageNumber=10 ) {
			$this->ql = $ql->rules( self::RULES )
			               ->range( self::RANGE );
			$this->pageNumber = $pageNumber;
		}

		public static function install ( QueryList $queryList , ...$opt ) {
			$name = $opt[0] ?? 'sogouWenwen';
			$queryList->bind( $name , function ( $pageNumber = 10 )
			{
				return new SogouWenwen( $this , $pageNumber );
			} );
		}

		public function setHttpOpt ( array $httpOpt = [] ) {
			$this->httpOpt = $httpOpt;
			return $this;
		}

		public function search ( $keyword ) {
			$this->keyword = $keyword;

			return $this;
		}

		public function page ( $page = 1 , $realURL = false ) {
			try {
				return $this->query( $page )
				            ->query()
				            ->getData( function ( $item ) use ( $realURL )
				            {
					            return $item;
				            } );
			}catch (\Exception $e){
				return [];
			}
		}

		public function getCount () {
			$count = 0;
			$text = $this->query( 1 )
			             ->find( '.num-tips' )
			             ->text();
			if ( preg_match( '/[\d,]+/' , $text , $arr ) ) {
				$count = str_replace( ',' , '' , $arr[0] );
			}
			return (int) $count;
		}

		public function getCountPage () {
			$count = $this->getCount();
			$countPage = ceil( $count / $this->pageNumber );
			return $countPage;
		}

		protected function query ( $page = 1 ) {
			try{
				$this->ql->get( self::API , [
					'query' => $this->keyword,
					'insite'=>'wenwen.sogou.com',
					'pid' => 'sogou-wsse-a9e18cb5dd9d3ab4',
					'rcer' => '',
					'ie' => 'utf8',
					'page' => $page,
				] , $this->httpOpt );
				return $this->ql;
			}catch (\Exception $e){
				return $this->ql;
			}
		}

		/**
		 * 获取搜狗跳转的真正地址
		 *
		 * @param string $url
		 * @return string
		 */
		protected function getRealURL ( $url ) {
			// 得到搜狗跳转的真正地址
			$header = get_headers( $url , 1 );
			if ( strpos( $header[0] , '301' ) || strpos( $header[0] , '302' ) ) {
				if ( is_array( $header['Location'] ) ) {
					// return $header['Location'][count($header['Location'])-1];
					return $header['Location'][0];
				}
				else {
					return $header['Location'];
				}
			}
			else {
				return $url;
			}
		}

	}