<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;

class ModelViewerController extends Controller
{
    private const MAX_FILES = 3000;

    public function index()
    {
        $groups = Cache::remember('model_viewer_list_v2', 600, fn () => $this->scan());

        return view('models.index', ['groups' => $groups]);
    }

    private function scan(): array
    {
        $root = public_path('fastdl/models');
        $files = array_merge(
            glob($root.'/*.mdl') ?: [],
            glob($root.'/*/*.mdl') ?: [],
            glob($root.'/*/*/*.mdl') ?: [],
        );

        $groups = [];
        foreach (array_slice($files, 0, self::MAX_FILES) as $file) {
            $name = basename($file);
            $lower = strtolower($name);

            // "<name>T.mdl" only carries textures for "<name>.mdl".
            if (str_ends_with($lower, 't.mdl') && is_file(dirname($file).'/'.substr($name, 0, -5).'.mdl')) {
                continue;
            }

            $relDir = trim(str_replace('\\', '/', substr(dirname($file), strlen($root))), '/');
            $group = $relDir === '' ? '(үндсэн)' : $relDir;

            $category = match (true) {
                (bool) preg_match('/^[vwp]_/i', $name) => 'weapon',
                str_starts_with($relDir, 'player') => 'player',
                default => 'prop',
            };

            $path = ($relDir === '' ? '' : implode('/', array_map('rawurlencode', explode('/', $relDir))).'/').rawurlencode($name);

            $groups[$group][] = [
                'name' => $name,
                'kb' => (int) ceil(filesize($file) / 1024),
                'url' => '/fastdl/models/'.$path,
                'category' => $category,
                'id' => ($relDir === '' ? '' : $relDir.'/').$name,
            ];
        }

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return $groups;
    }
}
