<?php
/**
 * Elastic Cloud handler for Monolog
 *
 * Handles all features of Elastic Cloud handler for Monolog.
 *
 * @package Handlers
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   2.4.0
 */

namespace Mailarchiver\Handler;

use MAMonolog\Logger;
use MAMonolog\Handler\ElasticsearchHandler;
use MAMonolog\Handler\HandlerInterface;
use MAMonolog\Formatter\FormatterInterface;
use Elasticsearch\Common\Exceptions\RuntimeException as ElasticsearchRuntimeException;
use Elastic\Elasticsearch\Client;

/**
 * Define the Monolog Elastic Cloud handler.
 *
 * Handles all features of Elastic Cloud handler for Monolog.
 *
 * @package Handlers
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   2.4.0
 */
class ElasticHandler extends ElasticsearchHandler {

	/**
	 * @param string     $url       The service url.
	 * @param string     $user      The deployment user.
	 * @param string     $pass      The deployment password.
	 * @param string     $index     The index name.
	 * @param int|string $level     The minimum logging level at which this handler will be triggered.
	 * @param bool       $bubble    Whether the messages that are handled can bubble up the stack or not.
	 *
	 * @since   2.4.0
	 */
	public function __construct( string $url, string $user, string $pass, string $index = '', $level = Logger::INFO, bool $bubble = true ) {
		if ( '' === $index ) {
			$index = 'mailarchiver';
		}
		$index   = strtolower( str_replace( [ ' ' ], '-', sanitize_text_field( $index ) ) );
		$client  = \Elastic\Elasticsearch\ClientBuilder::create()->setHosts( [ $url ] )->setBasicAuthentication( $user, $pass )->build();
		$options = [
			'index' => $index,
			'type'  => 'wordpress_mailarchiver',
		];
		parent::__construct( $client, $options, $level, $bubble );
	}

	/**
	 * Use Elasticsearch bulk API to send list of documents
	 *
	 * @param  array             $records
	 * @throws \RuntimeException
	 * @since   2.4.0
	 */
	protected function bulkSend( array $records ): void {
		try {
			$params = [
				'body' => [],
			];

			foreach ( $records as $record ) {
				$params['body'][] = [
					'index' => [
						'_index' => $record['_index'],
						'_type'  => $record['_type'],
					],
				];
				unset( $record['_index'], $record['_type'] );

				$params['body'][] = $record;
			}

			$responses = $this->client->bulk( $params );

			if ( true === $responses['errors'] ) {
				throw $this->createExceptionFromResponses( $responses );
			}
		} catch ( \Throwable $e ) {
			if ( 'Waiting did not resolve future' !== $e->getMessage() && ! $this->options['ignore_error'] ) {
				throw new \RuntimeException( 'Error sending messages to Elasticsearch', 0, $e );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDefaultFormatter(): FormatterInterface {
		return new \Mailarchiver\Formatter\ElasticCloudFormatter( $this->options['index'], $this->options['type'] );
	}
}
