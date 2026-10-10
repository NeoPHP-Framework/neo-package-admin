<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Controller;

use NeoPHP\Component\Http\Exception\NotFoundHttpException;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\NeoAdmin\Contract\AbstractAdminController;
use NeoPHP\NeoAdmin\Crud\EntityFormFactory;
use NeoPHP\NeoAdmin\Crud\EntityQuery;
use NeoPHP\NeoAdmin\Crud\FieldResolver;
use NeoPHP\NeoAdmin\Crud\ValueRenderer;
use NeoPHP\NeoAdmin\Definition\EntityDefinition;
use NeoPHP\NeoAdmin\Exception\NeoAdminException;
use NeoPHP\Package\Orm\OrmManagerInterface;
use Throwable;

class CrudController extends AbstractAdminController
{
    #[Route('/admin/{entity}', name: 'admin_crud', methods: ['GET'])]
    public function entry(string $entity): Response
    {
        return $this->entity($entity)->getDefault() === 'new' ? $this->new($entity) : $this->index($entity);
    }

    #[Route('/admin/{entity}/list', name: 'admin_crud_index', methods: ['GET'])]
    public function index(string $entity): Response
    {
        $definition = $this->entity($entity, 'index');
        $request = $this->get(Request::class);
        $fields = $this->get(FieldResolver::class)->resolve($definition, 'index');
        $search = trim((string) $request->query->get('q', ''));
        $sort = $request->query->get('sort');
        $direction = strtolower((string) $request->query->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = max(1, (int) (((array) $definition->getOption('index', []))['per_page'] ?? $this->admin()->getPerPage()));

        $paginator = $this->get(EntityQuery::class)->paginate($definition, $fields, $search, is_string($sort) ? $sort : null, $direction, $request->query->getInt('page', 1), $perPage);

        return $this->renderAdmin('@admin/crud/index.html.twig', [
            'entity' => $definition,
            'fields' => $fields,
            'paginator' => $paginator,
            'search' => $search,
            'searchable' => $this->get(FieldResolver::class)->search($definition) !== [],
            'actions' => $this->actions($definition),
        ], 200, $definition);
    }

    #[Route('/admin/{entity}/new', name: 'admin_crud_new', methods: ['GET', 'POST'])]
    public function new(string $entity): Response
    {
        $definition = $this->entity($entity, 'new');
        $factory = $this->get(EntityFormFactory::class);
        $object = $factory->instantiate($definition);
        $form = $factory->create($definition, $object, $this->get(FieldResolver::class)->resolve($definition, 'form'), $this->generateUrl('admin_crud_new', ['entity' => $definition->getSlug()]));
        $form->handleRequest($this->get(Request::class));

        if ($form->isSubmitted() && $form->isValid()) {
            $orm = $this->get(OrmManagerInterface::class);
            $orm->persist($object);
            $orm->flush();
            $this->addFlash('success', $this->translate('{entity} created.', ['entity' => $this->get(ValueRenderer::class)->label($object)], 'admin'));

            return $this->redirectAfterSave($definition, $object);
        }

        return $this->renderAdmin('@admin/crud/form.html.twig', [
            'entity' => $definition,
            'object' => null,
            'form' => $form,
            'actions' => $this->actions($definition),
        ], $form->isSubmitted() ? 422 : 200, $definition);
    }

    #[Route('/admin/{entity}/{id}', name: 'admin_crud_show', methods: ['GET'])]
    public function show(string $entity, string $id): Response
    {
        $definition = $this->entity($entity, 'show');

        return $this->renderAdmin('@admin/crud/show.html.twig', [
            'entity' => $definition,
            'object' => $this->find($definition, $id),
            'id' => $id,
            'fields' => $this->get(FieldResolver::class)->resolve($definition, 'show'),
            'actions' => $this->actions($definition),
        ], 200, $definition);
    }

    #[Route('/admin/{entity}/{id}/edit', name: 'admin_crud_edit', methods: ['GET', 'POST'])]
    public function edit(string $entity, string $id): Response
    {
        $definition = $this->entity($entity, 'edit');
        $object = $this->find($definition, $id);
        $form = $this->get(EntityFormFactory::class)->create($definition, $object, $this->get(FieldResolver::class)->resolve($definition, 'form'), $this->generateUrl('admin_crud_edit', ['entity' => $definition->getSlug(), 'id' => $id]));
        $form->handleRequest($this->get(Request::class));

        if ($form->isSubmitted() && $form->isValid()) {
            $this->get(OrmManagerInterface::class)->flush();
            $this->addFlash('success', $this->translate('{entity} updated.', ['entity' => $this->get(ValueRenderer::class)->label($object)], 'admin'));

            return $this->redirectAfterSave($definition, $object);
        }

        return $this->renderAdmin('@admin/crud/form.html.twig', [
            'entity' => $definition,
            'object' => $object,
            'id' => $id,
            'form' => $form,
            'actions' => $this->actions($definition),
        ], $form->isSubmitted() ? 422 : 200, $definition);
    }

    #[Route('/admin/{entity}/{id}/delete', name: 'admin_crud_delete', methods: ['POST'])]
    public function delete(string $entity, string $id): Response
    {
        $definition = $this->entity($entity, 'delete');
        $object = $this->find($definition, $id);
        $label = $this->get(ValueRenderer::class)->label($object);
        $request = $this->get(Request::class);
        $token = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('admin_delete_' . $definition->getSlug() . '_' . $id, is_string($token) ? $token : null)) {
            $this->addFlash('error', $this->translate('The security token is invalid, please try again.', [], 'admin'));

            return $this->redirectToRoute('admin_crud_index', ['entity' => $definition->getSlug()]);
        }

        try {
            $orm = $this->get(OrmManagerInterface::class);
            $orm->remove($object);
            $orm->flush();
            $this->addFlash('success', $this->translate('{entity} deleted.', ['entity' => $label], 'admin'));
        } catch (Throwable) {
            $this->addFlash('error', $this->translate('{entity} cannot be deleted: other records probably depend on it.', ['entity' => $label], 'admin'));
        }

        return $this->redirectToRoute($definition->hasAction('index') && $this->can($definition, 'index') ? 'admin_crud_index' : 'admin_dashboard', ['entity' => $definition->getSlug()]);
    }

    protected function entity(string $slug, ?string $action = null): EntityDefinition
    {
        try {
            $definition = $this->admin()->getEntityBySlug($slug);
        } catch (NeoAdminException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), [], $exception);
        }

        if ($action !== null && !$definition->hasAction($action)) {
            throw new NotFoundHttpException('The action "{action}" is disabled for the entity "{entity}".', ['action' => $action, 'entity' => $definition->getName()]);
        }

        $this->denyUnlessAdmin($definition->getRole());

        if ($action !== null && $definition->getActionRole($action) !== null) {
            $this->denyAccessUnlessGranted((string) $definition->getActionRole($action));
        }

        return $definition;
    }

    protected function find(EntityDefinition $definition, string $id): object
    {
        $object = $this->get(OrmManagerInterface::class)->find($definition->getClass(), $id);

        if ($object === null) {
            throw new NotFoundHttpException('The {entity} "{id}" does not exist.', ['entity' => $definition->getName(), 'id' => $id]);
        }

        return $object;
    }

    protected function can(EntityDefinition $definition, string $action): bool
    {
        $role = $definition->getActionRole($action);

        return $definition->hasAction($action) && ($role === null || $this->isGranted($role));
    }

    protected function actions(EntityDefinition $definition): array
    {
        $actions = [];

        foreach (EntityDefinition::ACTIONS as $action) {
            $actions[$action] = $this->can($definition, $action);
        }

        return $actions;
    }

    protected function redirectAfterSave(EntityDefinition $definition, object $object): Response
    {
        $id = (string) $this->get(OrmManagerInterface::class)->getMetadata($object)->getIdentifierValue($object);

        return match (true) {
            $this->can($definition, 'index') => $this->redirectToRoute('admin_crud_index', ['entity' => $definition->getSlug()]),
            $this->can($definition, 'show') => $this->redirectToRoute('admin_crud_show', ['entity' => $definition->getSlug(), 'id' => $id]),
            $this->can($definition, 'edit') => $this->redirectToRoute('admin_crud_edit', ['entity' => $definition->getSlug(), 'id' => $id]),
            default => $this->redirectToRoute('admin_dashboard'),
        };
    }
}