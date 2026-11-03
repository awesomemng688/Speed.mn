<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;

class ModelViewerController extends Controller
{
    public function index()
    {
        $groups = Cache::remember('model_viewer_list', 600, function () {
            $groups = [];
            $files = glob(public_path('fastdl/models/*/*.mdl')) ?: [];

            foreach (array_slice($files, 0, 3000) as $file) {
                $folder = basename(dirname($file));
                $name = basename($file);
                $groups[$folder][] = [
                    'name' => $name,
                    'kb' => (int) ceil(filesize($file) / 1024),
                    'url' => '/fastdl/models/'.rawurlencode($folder).'/'.rawurlencode($name),
                ];
            }

            ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

            return $groups;
        });

        return view('models.index', ['groups' => $groups]);
    }
}
