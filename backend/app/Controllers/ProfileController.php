<?php
declare(strict_types=1);

namespace TaskBuddy\Controllers;

use TaskBuddy\Services\CatalogService;
use TaskBuddy\Services\ProfileService;
use TaskBuddy\Support\ApiException;
use TaskBuddy\Support\Auth;
use TaskBuddy\Support\Request;
use TaskBuddy\Support\Response;
use TaskBuddy\Support\Validator;

final class ProfileController extends BaseController
{
    private ProfileService $profiles;

    public function __construct()
    {
        $this->profiles = new ProfileService();
    }

    public function show(Request $r, array $p): Response
    {
        return Response::ok($this->profiles->get(Auth::id()));
    }

    public function update(Request $r, array $p): Response
    {
        return Response::ok($this->profiles->update(Auth::id(), Auth::role(), $r->body));
    }

    public function provider(Request $r, array $p): Response
    {
        return Response::ok($this->profiles->publicProvider((int)$p['id']));
    }

    public function skills(Request $r, array $p): Response
    {
        $v = new Validator($r->body);
        $ids = $v->intList('skill_ids', true, 100);
        $v->fail();
        return Response::ok($this->profiles->setSkills(Auth::id(), $ids ?? []));
    }

    public function categories(Request $r, array $p): Response
    {
        $v = new Validator($r->body);
        $ids = $v->intList('category_ids', true, 50);
        $v->fail();
        return Response::ok($this->profiles->setCategories(Auth::id(), $ids ?? []));
    }

    public function availability(Request $r, array $p): Response
    {
        if (!array_key_exists('slots', $r->body)) {
            throw ApiException::validation(['slots' => 'This field is required.']);
        }
        return Response::ok($this->profiles->setAvailability(Auth::id(), $r->body['slots']));
    }

    public function listCategories(Request $r, array $p): Response
    {
        return Response::ok((new CatalogService())->categories());
    }

    public function listSkills(Request $r, array $p): Response
    {
        $cid = isset($r->query['category_id']) && is_numeric($r->query['category_id']) ? (int)$r->query['category_id'] : null;
        return Response::ok((new CatalogService())->skills($cid));
    }

    public function categorySkills(Request $r, array $p): Response
    {
        return Response::ok((new CatalogService())->skills((int)$p['id']));
    }
}
