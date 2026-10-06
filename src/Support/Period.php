<?php

namespace App\Support;

use DateTimeImmutable;

// periodo dos relatorios (task 59), lido da url: ?periodo=hoje|7dias|mes|intervalo (&de=AAAA-MM-DD&ate=AAAA-MM-DD).
// vira um intervalo [from, to) em horario local da aplicacao (o banco usa o mesmo fuso, ver Database),
// pronto pra comparar com created_at. sem periodo (ou "tudo") = sem filtro.
final class Period {
    public const OPTIONS = [
        'tudo'      => 'Desde o início',
        'hoje'      => 'Hoje',
        '7dias'     => 'Últimos 7 dias',
        'mes'       => 'Este mês',
        'intervalo' => 'Escolher datas',
    ];

    private const FORMAT = 'Y-m-d H:i:s';

    private function __construct(
        public readonly string $key,
        public readonly ?string $from,   // inclusivo, 'Y-m-d H:i:s'; null = sem filtro
        public readonly ?string $to,     // exclusivo
        public readonly string $start,   // datas do formulario ('' quando nao ha)
        public readonly string $end,
        public readonly bool $invalid,   // intervalo pedido com data errada: cai em "tudo" e a tela avisa
    ) {
    }

    public static function fromQuery(array $query, ?DateTimeImmutable $now = null): self {
        $now = $now ?? new DateTimeImmutable('now');
        $today = $now->setTime(0, 0);
        $key = (string)($query['periodo'] ?? 'tudo');

        return match ($key) {
            'hoje'      => self::range('hoje', $today, $today),
            '7dias'     => self::range('7dias', $today->modify('-6 days'), $today),
            'mes'       => self::range('mes', $today->modify('first day of this month'), $today),
            'intervalo' => self::interval((string)($query['de'] ?? ''), (string)($query['ate'] ?? '')),
            default     => new self('tudo', null, null, '', '', false),
        };
    }

    public function isFiltered(): bool {
        return $this->from !== null;
    }

    // parametros pra manter o periodo nos links (paginacao, exportacao)
    public function query(): array {
        if (!$this->isFiltered()) {
            return [];
        }
        return $this->key === 'intervalo'
            ? ['periodo' => 'intervalo', 'de' => $this->start, 'ate' => $this->end]
            : ['periodo' => $this->key];
    }

    // primeiro e ultimo dia, os dois inclusivos
    private static function range(string $key, DateTimeImmutable $first, DateTimeImmutable $last): self {
        return new self(
            $key,
            $first->format(self::FORMAT),
            $last->modify('+1 day')->format(self::FORMAT),
            $first->format('Y-m-d'),
            $last->format('Y-m-d'),
            false
        );
    }

    private static function interval(string $start, string $end): self {
        $first = self::date($start);
        $last = self::date($end);
        if ($first === null || $last === null || $first > $last) {
            return new self('tudo', null, null, '', '', true);
        }
        return self::range('intervalo', $first, $last);
    }

    // so AAAA-MM-DD de verdade (2026-02-30 nao vale)
    private static function date(string $value): ?DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
