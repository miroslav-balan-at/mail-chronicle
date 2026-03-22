<?php
/**
 * Email Query Value Object
 *
 * Encapsulates all parameters for a paginated email list query.
 * Immutable — created once and passed to the repository.
 *
 * @package MailChronicle
 */

declare(strict_types=1);

namespace MailChronicle\Common\Query;

/**
 * Value object that carries all parameters for querying the email log table.
 */
final class EmailQuery {

	public readonly int $per_page;
	public readonly int $page;
	public readonly string $orderby;
	public readonly string $order;
	public readonly string $status;
	public readonly string $provider;
	public readonly string $search;
	public readonly string $date_from;
	public readonly string $date_to;

	/**
	 * Allowed columns for ORDER BY — prevents SQL injection via user input.
	 */
	private const ALLOWED_ORDERBY = [
		'sent_at',
		'created_at',
		'updated_at',
		'recipient',
		'subject',
		'status',
		'provider',
	];

	/**
	 * @param array<string, mixed> $args Raw arguments (typically from wp_parse_args).
	 */
	public function __construct( array $args = [] ) {
		$per_page = $args['per_page'] ?? 20;
		$page     = $args['page'] ?? 1;
		$orderby  = $args['orderby'] ?? 'sent_at';
		$order    = $args['order'] ?? 'DESC';

		$this->per_page  = is_int( $per_page ) && $per_page > 0 ? $per_page : 20;
		$this->page      = is_int( $page ) && $page > 0 ? $page : 1;
		$this->orderby   = in_array( $orderby, self::ALLOWED_ORDERBY, true ) ? $orderby : 'sent_at';
		$this->order     = strtoupper( is_string( $order ) ? $order : 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$this->status    = is_string( $args['status'] ?? null ) ? $args['status'] : '';
		$this->provider  = is_string( $args['provider'] ?? null ) ? $args['provider'] : '';
		$this->search    = is_string( $args['search'] ?? null ) ? $args['search'] : '';
		$this->date_from = is_string( $args['date_from'] ?? null ) ? $args['date_from'] : '';
		$this->date_to   = is_string( $args['date_to'] ?? null ) ? $args['date_to'] : '';
	}

	/**
	 * Byte offset for the SQL LIMIT clause.
	 */
	public function offset(): int {
		return ( $this->page - 1 ) * $this->per_page;
	}
}
