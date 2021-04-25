<?php
	/**
	 *
	 * Created by Wangqs
	 * Date: 2021/4/25 09:34
	 */

	namespace QL\Ext;

	use QL\Contracts\PluginContract;
	use QL\QueryList;


	class Sogou implements PluginContract
	{

		/**
		 * QueryList对象
		 *
		 * @var QueryList
		 */
		protected $queryList;

		/**
		 * 搜索关键词
		 *
		 * @var string
		 */
		protected $keyword;

		/**
		 * 每页条数
		 *
		 * @var integer
		 */
		protected $perPage = 10;

		/**
		 * HTTP选项
		 *
		 * @var array
		 */
		protected $httpOpt = [];

		/**
		 * 请求地址
		 *
		 * @var string
		 */
		const API = 'https://www.sogou.com/web';

		/**
		 * 采集规则
		 *
		 * @var array
		 */
		const RULES = [
			'title' => [
				'h3>a' ,
				'text'
			] ,
			'link'  => [
				'h3>a' ,
				'href'
			]
		];

		/**
		 * 切片选择器
		 *
		 * @var string
		 */
		const RANGE = '.results>div';

		/**
		 * 初始化QueryList对象
		 *
		 * @param QueryList $queryList
		 * @param int       $perPage
		 */
		public function __construct ( QueryList $queryList , int $perPage = null ) {
			$this->queryList = $queryList->rules( self::RULES )
			                             ->range( self::RANGE );
			$this->perPage = $perPage;
		}

		/**
		 * 装载插件
		 *
		 * @param QueryList $queryList
		 * @param mixed     ...$opt
		 */
		public static function install ( QueryList $queryList , ...$opt ) {
			$name = $opt[0] ?? 'sogou';
			$queryList->bind( $name , function ( $perPage = 10 )
			{
				return new Sogou( $this , $perPage );
			} );
		}

		/**
		 * 设置HTTP选项
		 *
		 * @param array $httpOpt
		 */
		public function setHttpOpt ( array $httpOpt = [] ) {
			$this->httpOpt = $httpOpt;
			return $this;
		}

		/**
		 * 设置搜索关键词
		 *
		 * @param string $keyword
		 */
		public function search ( string $keyword ) {
			$this->keyword = $keyword;
			return $this;
		}

		/**
		 * 获取搜索结果
		 *
		 * @param int     $page
		 * @param boolean $realURL
		 */
		public function page ( int $page = 1 , bool $realURL = false ) {
			return $this->query( $page )
			            ->query()
			            ->getData( function ( $item ) use ( $realURL )
			            {
				            $realURL && $item['link'] = $this->getRealURL( $item['link'] );
				            return $item;
			            } );
		}

		/**
		 * 获取相关搜索
		 *
		 */
		public function getSuggestions () {
			// 选择器
			$table = $this->query()
			              ->find( '.hintBox>table' );

			// 获取记录
			$rows = $table->find( 'tr' )
			              ->map( function ( $row )
			              {
				              return $row->find( 'td' )
				                         ->texts()
				                         ->all();
			              } );

			return $rows->collapse();
		}

		/**
		 * 获取搜索结果总条数
		 *
		 * @return integer
		 */
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

		/**
		 * 获取搜索结果总页数
		 *
		 * @return integer
		 */
		public function getCountPage () {
			$count = $this->getCount();
			$countPage = ceil( $count / $this->perPage );

			return $countPage;
		}

		/**
		 * 获取原始数据
		 *
		 * @param number $page
		 * @return QueryList
		 */
		protected function query ( int $page = 1 ) {
			$this->queryList->get( self::API , [
				'query' => $this->keyword ,
				'page'  => $page ,
				'num'   => $this->perPage ,
				'ie'    => 'utf8'
			] , $this->httpOpt );

			return $this->queryList;
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