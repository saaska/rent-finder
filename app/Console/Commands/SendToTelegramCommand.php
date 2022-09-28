<?php

namespace App\Console\Commands;

use App\Models\Flat;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LanguageDetection\Language;

class SendToTelegramCommand extends Command
{
    protected $signature = 'send:telegram {districtId}';
    protected $description = 'Send new flats to telegram channel';

    /**
     * @throws Exception
     */
    public function handle()
    {
        $flat = Flat::whereDistrictId($this->argument('districtId'))
            ->whereNull('published_at')
            ->whereNull('error_at')
            ->first();

        if(!$flat) {
            $this->info('Квартир для публикации не найдено');
            return;
        }

        $text = "*[{$this->escapeChars($flat->title)}](https://ss.ge$flat->link)*\n\n";
        if ($flat->description) {
            $description = $this->getTranslateDescription($flat->description);
            $text .= $this->escapeChars($description) . "\n\n";
        }
        $text .= 'Адрес: ' . $this->escapeChars($flat->address) . "\n";
        $text .= 'Площадь: ' . $flat->flat_area . "\n";
        $text .= 'Дом: ' . $flat->flat_type . "\n";
        $text .= 'Этаж: ' . $flat->flat_floor . "\n\n";
        $text .= 'Цена: ' . $flat->price . "\n\n";
        $text .= "[Подробности и контакты](https://ss.ge$flat->link)";

        $rawPhotos = json_decode($flat->photos);
        if(count($rawPhotos)) {
            // album with photos
            $photos = [];
            foreach ($rawPhotos as $photo) {
                $photos[] = [
                    'type' => 'photo',
                    'media' => $photo,
                ];
            }
            $photos[0]['parse_mode'] = 'MarkdownV2';
            $photos[0]['caption'] = $text;

            $result = Http::asJson()
                ->post('https://api.telegram.org/bot5657009028:AAFsr2wbTnJ9V369DQdByUc8VcoIOAubWSg/sendMediaGroup', [
                    'chat_id' => $flat->district->channel_id,
                    'media' => $photos,
                ])
                ->json();
        } else {
            // text
            $result = Http::asJson()
                ->post('https://api.telegram.org/bot5657009028:AAFsr2wbTnJ9V369DQdByUc8VcoIOAubWSg/sendMessage', [
                    'chat_id' => $flat->district->channel_id,
                    'text' => $text,
                    'parse_mode' => 'MarkdownV2'
                ])
                ->json();
        }

        if (
            array_key_exists('ok', $result) &&
            $result['ok'] === true
        ) {
            $flat->published_at = now();
            $flat->save();
            $this->info('Квартира: ' . $flat->title . ' – успешно опубликована');
        } else {
            if ($result['error_code'] == 400) {
                $flat->error_at = now();
                $flat->save();
            }
            throw new Exception($result['description']);
        }
    }

    /**
     * @throws Exception
     */
    private function getTranslateDescription(string $text): string
    {
        $ld = new Language();
        $languages = $ld->detect($text)->close();

        if ($languages['ru'] > 0.1) {
            return $text;
        }

        // translate
        $translate = Http::asJson()
            ->withToken('AQVNxo6Z3rcWLYNxHYfm23wmcwqYpEPbOm-QIEzq', 'Api-Key')
            ->post('https://translate.api.cloud.yandex.net/translate/v2/translate', [
                'targetLanguageCode' => 'ru',
                'texts' => [$text],
                'folderId' => 'b1gk5psvihgupg93uirb',
            ])
            ->json();

        if (array_key_exists('translations', $translate)) {
            return $translate['translations'][0]['text'];
        }

        throw new Exception($translate['message']);
    }

    private function escapeChars($text): string
    {
        $chars = [
            '+', '-', '.', '!', '(', ')', '{', '}', '#',
        ];

        $replace = [];
        foreach($chars as $char) {
            $replace[] = '\\'.$char;
        }

        return Str::replace($chars, $replace, $text);
    }
}
