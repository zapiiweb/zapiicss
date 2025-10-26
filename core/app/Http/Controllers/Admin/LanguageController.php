<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Frontend;
use Illuminate\Http\Request;
use App\Models\Language;
use App\Rules\FileTypeValidate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class LanguageController extends Controller
{
    public function langManage($lang = false)
    {
        $pageTitle = 'Language Manager';
        $languages = Language::orderBy('is_default', 'desc')->get();
        return view('admin.language.lang', compact('pageTitle', 'languages'));
    }

    public function langStore(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:40',
            'code'  => 'required|string|max:40|unique:languages',
            'info'  => 'nullable|string',
            'image' => ['required', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])]
        ]);

        $data     = file_get_contents(resource_path('lang/') . 'en.json');
        $jsonFile = strtolower($request->code) . '.json';
        $path     = resource_path('lang/') . $jsonFile;

        File::put($path, $data);

        $language = new  Language();
        if ($request->hasFile('image')) {
            try {
                $language->image = fileUploader($request->image, getFilePath('language'), getFileSize('language'));
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload language image'];
                return back()->withNotify($notify);
            }
        }

        if ($request->is_default) {
            $lang = $language->where('is_default', Status::YES)->first();
            if ($lang) {
                $lang->is_default = Status::NO;
                $lang->save();
            }
        }

        $language->name       = $request->name;
        $language->info       = $request->info ?? null;
        $language->code       = strtolower($request->code);
        $language->is_default = $request->is_default ? Status::YES : Status::NO;
        $language->save();

        $notify[] = ['success', 'Language added successfully'];
        return back()->withNotify($notify);
    }

    public function langUpdate(Request $request, $id)
    {
        $request->validate([
            'name'  => 'required',
            'info'  => 'nullable|string',
            'image' => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])]
        ]);

        $language = Language::findOrFail($id);
        if (!$request->is_default) {
            $defaultLang = Language::where('is_default', Status::YES)->where('id', '!=', $id)->exists();
            if (!$defaultLang) {
                $notify[] = ['error', 'You\'ve to set another language as default before unset this'];
                return back()->withNotify($notify);
            }
        }

        $language->name       = $request->name;
        $language->info       = $request->info ?? null;
        $language->is_default = $request->is_default ? Status::YES : Status::NO;

        if ($request->hasFile('image')) {
            try {
                $old             = $language->image;
                $language->image = fileUploader($request->image, getFilePath('language'), getFileSize('language'), $old);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload language image'];
                return back()->withNotify($notify);
            }
        }
        $language->save();

        if ($request->is_default) {
            $lang = Language::where('is_default', Status::YES)->where('id', '!=', $language->id)->first();
            if ($lang) {
                $lang->is_default = Status::NO;
                $lang->save();
            }
        }
        $notify[] = ['success', 'Language updated successfully'];
        return back()->withNotify($notify);
    }

    public function langDelete($id)
    {
        $lang = Language::find($id);

        if ($lang->is_default) {
            $notify[] = ['error', 'You can not delete the default language.'];
            return back()->withNotify($notify);
        }

        fileManager()->removeFile(resource_path('lang/') . $lang->code . '.json');
        fileManager()->removeFile(getFilePath('language') . '/' . $lang->image);
        $lang->delete();
        $notify[] = ['success', 'Language deleted successfully'];
        return back()->withNotify($notify);
    }

    public function langEdit($id)
    {
        $lang      = Language::findOrFail($id);
        $pageTitle = "Update " . $lang->name . " Keywords";
        $filePath  = resource_path('lang/') . $lang->code . '.json';

        if (!file_exists($filePath)) {
            $notify[] = ['error', 'File not found'];
            return back()->withNotify($notify);
        }
        $keywords = json_decode(file_get_contents($filePath), true);

        if (is_null($keywords)) {
            $keywords = [];
            file_put_contents($filePath, "{}");
        }
        $search = request()->search;

        if ($search) {
            $keywords = array_filter(
                $keywords,
                function ($key) use ($search) {
                    return stripos($key, $search) !== false;
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        $languages = Language::where('id', '!=', $lang->id)->get();
        return view('admin.language.edit_lang', compact('pageTitle', 'keywords', 'lang', 'languages'));
    }

    public function langImport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_lang_id' => 'required|exists:languages,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->all()
            ]);
        }

        $tolang = Language::find($request->to_lang_id);

        if ($request->id != 999) {
            $fromLang = Language::find($request->id);
            if (!$fromLang) {
                $notify[] = ['error', 'The from language not found'];
                return back()->withNotify($notify);
            }
            $json     = file_get_contents(resource_path('lang/') . $fromLang->code . '.json');
            $keywords = json_decode($json, true);
        } else {
            $text     = $this->getKeys();
            $keywords = explode("\n", $text);
        }

        $items = file_get_contents(resource_path('lang/') . $tolang->code . '.json');
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            if (!array_key_exists($keyword, json_decode($items, true))) {
                $newArr[$keyword] = $keyword;
            }
        }
        if (isset($newArr)) {
            $itemData = json_decode($items, true);
            $result   = array_merge($itemData, $newArr);
            file_put_contents(resource_path('lang/') . $tolang->code . '.json', json_encode($result));
        }

        $notify[] = ['success', 'Import data successfully'];
        return back()->withNotify($notify);
    }

    public function storeLanguageJson(Request $request, $id)
    {
        $lang = Language::findOrFail($id);
        $request->validate([
            'key'   => 'required',
            'value' => 'required'
        ]);

        $items  = file_get_contents(resource_path('lang/') . $lang->code . '.json');
        $reqKey = trim($request->key);

        if (array_key_exists($reqKey, json_decode($items, true))) {
            $notify[] = ['error', "Key already exist"];
            return back()->withNotify($notify)->withInput();
        } else {
            $newArr[$reqKey] = trim($request->value);
            $itemData        = json_decode($items, true);
            $result          = array_merge($itemData, $newArr);
            file_put_contents(resource_path('lang/') . $lang->code . '.json', json_encode($result));
            $notify[] = ['success', "Language key added successfully"];
            return back()->withNotify($notify);
        }
    }
    public function deleteLanguageJson(Request $request, $id, $key)
    {

        $lang    = Language::findOrFail($id);
        $data    = file_get_contents(resource_path('lang/') . $lang->code . '.json');
        $jsonArr = json_decode($data, true);


        unset($jsonArr[urldecode($key)]);

        file_put_contents(resource_path('lang/') . $lang->code . '.json', json_encode($jsonArr));
        $notify[] = ['success', "Language key deleted successfully"];
        return back()->withNotify($notify);
    }

    public function updateLanguageJson(Request $request, $id)
    {
        $lang = Language::findOrFail($id);
        $request->validate([
            'keyword' => 'required|array|min:1',
        ]);

        $filePath = resource_path('lang/') . $lang->code . '.json';
        $keywords = json_decode(file_get_contents($filePath), true);

        foreach ($request->keyword as $keyword => $keywordValue) {
            if (array_key_exists($keyword, $keywords)) {
                $keywords[$keyword] = $keywordValue;
            }
        }

        file_put_contents($filePath, json_encode($keywords));
        $notify[] = ['success', 'Language key updated successfully'];
        return back()->withNotify($notify);
    }

    public function getKeys()
    {
        $langKeys = [];
        $dirname  = resource_path('views');
        foreach ($this->getAllFiles($dirname) as $file) {
            $langKeys = array_merge($langKeys, $this->getLangKeys($file));
        }
        $frontendData = Frontend::where('data_keys', '!=', 'seo.data')->get();
        foreach ($frontendData as $frontend) {
            foreach ($frontend->data_values as $key => $frontendValue) {
                if ($key != 'has_image' && !isImage($frontendValue) && !isHtml($frontendValue)) {
                    if (!$this->checkSpecialCharter($frontendValue)) {
                        $langKeys[] = $frontendValue;
                    }
                }
            }
        }
        $langKeys = array_unique($langKeys);
        $keyText  = '';
        foreach ($langKeys as $langKey) {
            $keyText .= "$langKey \n";
        }
        return rtrim($keyText, "\n");
    }


    private function getAllFiles($dir)
    {
        $root = $dir;

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iter as $path => $dir) {
            if (!$dir->isDir() && substr($dir, -4) == '.php') {
                $files[] = $path;
            }
        }
        return $files;
    }

    private function getLangKeys($path)
    {
        $code = file_get_contents($path);
        $exp  = explode("')", $code);
        $finalcode = '';
        foreach ($exp as $dd) {
            $finalcode .= $dd . "')

            ";
        }
        preg_match_all("/@lang\(\\'(.*)\\'\)/",  $finalcode, $keys);
        return $this->fixMultiIssue($keys[1]);
    }

    private function fixMultiIssue($arr)
    {
        $res = array();
        foreach ($arr as $keys) {
            $exp = explode("')", $keys);
            foreach ($exp as $child) {
                if (!strpos($child, '@lang') && !strpos($child, '}') && !strpos($child, '<') && !strpos($child, '{') && !strpos($child, '>')) {
                    $res[] = $child;
                }
            }
        }
        return $res;
    }

    public function checkSpecialCharter($string)
    {
        return false;
        $specialCharacters = [
            '+',
            '-',
            '*',
            '/',
            '%',
            '=',
            '+=',
            '-=',
            '*=',
            '/=',
            '%=',
            '==',
            '===',
            '!=',
            '<>',
            '!==',
            '<',
            '>',
            '<=',
            '>=',
            '&&',
            '||',
            '!',
            '++',
            '--',
            '.',
            '.=',
            '?:',
            '@',
            '&',
            '|',
            '^',
            '~',
            '<<',
            '>>',
            '->',
            '::',
            '$',
            '*',
            '[]',
            '{}',
            '()',
            ';',
            ',',
            "#",
            '@',
            '$'
        ];

        return in_array($string, $specialCharacters);
    }
}
