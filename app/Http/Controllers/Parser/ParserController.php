<?php

namespace App\Http\Controllers\Parser;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\EuroAutoLinks;
use App\Models\Models;
use App\Models\PackageConnection;
use App\Models\RefModels;
use App\Models\TemporarySearchResults;
use App\Models\Version;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ParserController extends Controller
{
    const DEFAULT_COUNT_IN_PACKAGE = 100;

    public function getVersion()
    {
        return json_encode([
            "currentVersion" => TemporarySearchResults::getCurrentVersion()
        ]);
    }

    public function getPackageCount(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'self_version' => 'required|integer|min:0',
            'elements_in_package' => 'integer|min:0',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), 400);
        }

        try {
            $arRequest = json_decode($request->getContent(), true);
            $version = $arRequest['self_version'];
            $elementsInPackage = $arRequest['elements_in_package'] ?? self::DEFAULT_COUNT_IN_PACKAGE;
            $currentVersion = TemporarySearchResults::getCurrentVersion();
            if ($version >= $currentVersion) {
                return response('Your version not need to update', 400);
            }

            $totalResultCount = TemporarySearchResults::getCountResultByVersion($version);
            $packageCount = ceil($totalResultCount / $elementsInPackage);
            return json_encode([
                'package_count' => $packageCount
            ]);
        } catch (\Exception $e) {
            return response(json_encode([
                'error' => $e->getMessage()
            ]), 400);
        }
    }

    public function getConnectionInfo(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'connection_key' => 'required',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), 400);
        }
        $arRequest = json_decode($request->getContent(), true);
        $connection = PackageConnection::where('key', $arRequest['connection_key'])->first();
        if (!$connection) {
            return response('Connection_key does\'t exist', 404);
        }

        return json_encode([
            'version_from' => $connection->version_from,
            'elements_count' => $connection->elements_count,
            'elements_in_package' => $connection->elements_in_package,
            'created_at' => $connection->created_at,
        ]);
    }

    public function getPackageByNumber(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'connection_key' => 'required',
            'package_number' => 'required|integer|min:0',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), 400);
        }
        try {
            $arRequest = json_decode($request->getContent(), true);
            $connection = PackageConnection::where('key', $arRequest['connection_key'])->first();
            if (!$connection) {
                return response('Connection_key does\'t exist', 404);
            }
            $packageResults = TemporarySearchResults::getPackageResults($arRequest['package_number'], $connection);
            return response()->json([
                'results' => $packageResults
            ]);
        } catch (\Exception $e) {
            return response(json_encode([
                'error' => $e->getMessage()
            ]), 400);
        }
    }

    public function getConnectionId(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'self_version' => 'required|numeric',
            'elements_in_package' => 'numeric',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), 400);
        }
        try {
            $arRequest = json_decode($request->getContent(), true);
            $version = $arRequest['self_version'];
            $elementsInPackage = $arRequest['elements_in_package'] ?? self::DEFAULT_COUNT_IN_PACKAGE;
            $currentVersion = TemporarySearchResults::getCurrentVersion();
            if ($version >= $currentVersion) {
                return response('Your version not need to update', 400);
            }
            $totalResultCount = TemporarySearchResults::getCountResultByVersion($version);
            $connection = PackageConnection::createConnectionByElementsCount($version, $elementsInPackage, $totalResultCount);
            return json_encode([
                'connection_key' => $connection->key
            ]);

        } catch (\Exception $e) {
            return response(json_encode([
                'error' => $e->getMessage()
            ]), 400);
        }
    }

    public function getNewVersionNum(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'element_count' => 'required|numeric',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), 400);
        }
        $arRequest = json_decode($request->getContent(), true);
        $elementCount = $arRequest['element_count'];

        $newVersion = new Version();
        $newVersion->version = Version::getNextEmptyVersion();
        $newVersion->element_count = $elementCount;
        $newVersion->save();

        try {
            return json_encode([
                'version' => $newVersion->version
            ]);

        } catch (\Exception $e) {
            return response(json_encode([
                'error' => $e->getMessage()
            ]), 400);
        }
    }

    public function zapchastiEvent(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'action' => 'required',
            'brand_id' => 'required|numeric',
            'model_id' => 'required|numeric',
            'generation_id' => 'numeric|nullable',
            'type_id' => 'numeric|nullable',
        ]);
        if ($validation->fails()) {
            return response($validation->errors()->toArray(), 400);
        }

        $params = json_decode($request->getContent(), true);
        if ($params['action'] != 'insert' && $params['action'] != 'delete') {
            return response(json_encode([
                'error' => sprintf("Action: \"%s\" is not define", $params['action'])
            ]), 400);
        }

        $link = null;
        $action = $params['action'];
        unset($params['action']);

        $brand = Brand::where('r_brand_id', $params['brand_id'])->first();
        $model = Models::where('r_model_id', $params['model_id'])->first();
        if (!$brand || !$model) {
            return response(json_encode([
                'error' => "Brand or model not found"
            ]), 400);
        }
        $paramsForQuery = [];
        $paramsForQuery['brand_id'] = $brand->id;
        $paramsForQuery['model_id'] = $model->id;
        $paramsForQuery['body_id'] = $params['type_id'];
        $paramsForQuery['generation_id'] = $params['generation_id'];
        $paramsForQuery['engine_id'] = $params['engine_id'];

        $car = RefModels::where(array_filter($paramsForQuery))->first();
        if (!$car) {
            return response(json_encode([
                'error' => "Car not found"
            ]), 400);
        }

        $link = EuroAutoLinks::whereRootModelLink($car->parse_link)->first();
        if (!$link) {
            return response(json_encode([
                'error' => sprintf("Link not found", $car->parse_link)
            ]), 400);
        }

        if ($link->is_recived) {
            return response(json_encode([
                'status' => 'ok',
            ]));
        }
        return response(json_encode(['error' => 'Spare parts not found']), 400);
    }
}