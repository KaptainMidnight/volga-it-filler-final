<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FillerCommand extends Command
{
    private HttpClientInterface $client;
    private string $server;
    private string $gameId;
    private string $playerId;
    private array $colorsSafe;
    private int $width;

    private const COLORS = [
        "#ff0000", // red
        "#ffff00", // yellow
        "#ffffff", // white
        "#00ff00", // green
        "#00ffff", // cyan
        "#0000ff", // blue
        "#ff00ff", // magenta
    ];

    protected function configure(): void
    {
        $this->client = HttpClient::create();

        $this->setName("start:game");
        $this->setDescription("The AI for play in filler game");
        $this->addOption("gameServer", null, InputOption::VALUE_REQUIRED, 'Game server to connect');
        $this->addOption("gameId", null, InputOption::VALUE_REQUIRED, 'The game id for connect');
        $this->addOption("playerId", null, InputOption::VALUE_REQUIRED, 'The player id for game');
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->server = $input->getOption('gameServer');
        $this->gameId = $input->getOption('gameId');
        $this->playerId = $input->getOption('playerId');
        $connectionResult = $this->connectToGame();
        $this->colorsSafe = [];

        // Проверяем на установку соединения с API
        if (!is_array($connectionResult)) {
            $output->writeln("<error>{$connectionResult}</error>");
            return self::FAILURE;
        }

        $this->width = $connectionResult['field']['width'];
        $output->writeln("<info>Подключился к сереверу!</info>");
        $output->writeln($this->move($connectionResult));
        return self::SUCCESS;
    }

    /**
     * Проверка на допустимые цвета для заполнения
     *
     * @param array $game
     * @param string $color
     * @return bool
     */
    private function isUsefulColor(array $game, string $color): bool
    {
        return $game and !($game['players'][$game['currentPlayerId']]['color'] === $color) and
            !($game['players'][($game['currentPlayerId'] % 2) + 1]['color'] === $color);
    }

    /**
     * Генерируем "безопасные" цвета, которые можно использовать
     *
     * @param array $game
     * @return array
     */
    private function safeColors(array $game): array
    {
        foreach (self::COLORS as $color) {
            if ($this->isUsefulColor($game, $color)) {
                $this->colorsSafe[] = $color;
            }
        }

        return $this->colorsSafe;
    }

    /**
     * Конвертируем обычный массив в матрицу
     *
     * @param array $cells
     * @return array
     */
    private function convertMatrix(array $cells): array
    {
        return array_chunk($cells, $this->width);
    }

    /**
     * Алгоритм игры будет очень простой, но в свое время сильный.
     * Надо будет всегда захватывать как можно больше ромбиков и как можно выше подняться
     * прежде чем брать блоки по горизонатли. Не надо будет ориентирвать свой цвет на количество блоков, которые
     * я могу взять в этот ход, выбирать надо буде цвет, который может взять стратегические блоки.
     * Так же можно использовать тот факт, что я могу заблокировать идеальных ход противника, переключившись
     * на этот цвет, в свою пользу
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    private function move(array $field): int|string
    {
        $matrix = $this->convertMatrix($field['field']['cells']);
        foreach ($matrix as $m) {
            for ($i = 0; $i <= 2; $i++) {
                // Если последний элемент из первой строчки равен последнему элементу из второй строчки и playerId != нашему,
                // то красим в этот цвет следующую клеточку
                if (end($m)['playerId'] === $this->playerId &&
                    end($matrix)['playerId'] === $this->playerId ||
                    end($matrix[$i + 1])['playerId'] == 0) {
                    if (end($matrix) === $m) {
                        $safe = $this->safeColors($field);
                        $color = $safe[rand(0, count($safe))]; // Да, настолько оригинальный алгоритм
                        $data = [
                            'playerId' => $this->playerId,
                            'color' => $color
                        ];

                        $response = $this->client->request('PUT', "{$this->server}game/{$this->gameId}", [
                            'json' => $data,
                        ]);

                        match ($response->getStatusCode()) {
                            400 => "Incorrect request parameters",
                            403 => "Provided player can't make a move right now",
                            409 => "Provided player can't choose this color right now",
                            404 => "Incorrect game id",
                            default => "Error"
                        };
                    }
                }
            }
        }

        exit($field['winnerPlayerId']);
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    private function connectToGame(): array|string
    {
        $response = $this->client->request('GET', "{$this->server}game/{$this->gameId}");
        return match ($response->getStatusCode()) {
            400 => "Incorrect request parameters",
            404 => "Incorrect game id",
            200 => $response->toArray(),
        };
    }
}
