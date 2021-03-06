<?php

namespace Specialtactics\L5Api\Http\Controllers;

use App\Models\BaseModel;
use App\Services\RestfulService;
use App\Transformers\BaseTransformer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Config;
use Validator;
use Specialtactics\L5Api\Transformers\RestfulTransformer;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class RestfulController extends BaseRestfulController
{
    /**
     * Request to retrieve a collection of all items of this resource
     *
     * @return \Dingo\Api\Http\Response
     */
    public function getAll()
    {
        $model = new static::$model;

        $query = $model::with($model::$localWith);
        $this->qualifyCollectionQuery($query);
        $resources = $query->get();

        return $this->response->collection($resources, $this->getTransformer());
    }

    /**
     * Request to retrieve a single item of this resource
     *
     * @param string $uuid UUID of the resource
     * @return \Dingo\Api\Http\Response
     * @throws HttpException
     */
    public function get($uuid)
    {
        $model = new static::$model;

        $resource = $model::with($model::$localWith)->where($model->getKeyName(), '=', $uuid)->first();

        if ( ! $resource) {
            throw new NotFoundHttpException('Resource \'' . class_basename(static::$model) . '\' with given UUID ' . $uuid . ' not found');
        }

        $this->authorizeUserAction('view', $resource);

        return $this->response->item($resource, $this->getTransformer());
    }

    /**
     * Request to create a new resource
     *
     * @param Request $request
     * @return \Dingo\Api\Http\Response
     * @throws HttpException|QueryException
     */
    public function post(Request $request)
    {
        $this->authorizeUserAction('create');

        $model = new static::$model;

        $this->restfulService->validateResource($model, $request->input());

        $resource = $this->restfulService->persistResource(new $model($request->input()));

        // Retrieve full model
        $resource = $model::with($model::$localWith)->where($model->getKeyName(), '=', $resource->getKey())->first();

        if ($this->shouldTransform()) {
            $response = $this->response->item($resource, $this->getTransformer())->setStatusCode(201);
        } else {
            $response = $resource;
        }

        return $response;
    }

    public function put($object)
    {
    }

    /**
     * Request to update the specified resource
     *
     * @param string $uuid UUID of the resource
     * @param Request $request
     * @return \Dingo\Api\Http\Response
     * @throws HttpException
     */
    public function patch($uuid, Request $request)
    {
        $model = static::$model::findOrFail($uuid);

        $this->authorizeUserAction('update', $model);

        $this->restfulService->validateResourceUpdate($model, $request->input());

        $this->restfulService->patch($model, $request->input());

        if ($this->shouldTransform()) {
            $response = $this->response->item($model, $this->getTransformer());
        } else {
            $response = $model;
        }

        return $response;
    }

    /**
     * Deletes a resource by UUID
     *
     * @param string $uuid UUID of the resource
     * @return \Dingo\Api\Http\Response
     * @throws NotFoundHttpException
     */
    public function delete($uuid)
    {
        $model = static::$model::findOrFail($uuid);

        $this->authorizeUserAction('delete', $model);

        $deletedCount = $model->delete();

        if ($deletedCount < 1) {
            throw new NotFoundHttpException('Could not find a resource with that UUID to delete');
        }

        return $this->response->noContent()->setStatusCode(204);
    }
}