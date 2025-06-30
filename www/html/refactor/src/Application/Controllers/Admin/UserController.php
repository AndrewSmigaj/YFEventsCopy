<?php

declare(strict_types=1);

namespace YakimaFinds\Application\Controllers\Admin;

use YakimaFinds\Application\Controllers\BaseController;
use YakimaFinds\Application\Http\Request;
use YakimaFinds\Application\Http\Response;
use YakimaFinds\Application\Services\UserService;
use YakimaFinds\Application\Validation\UserValidator;

class UserController extends BaseController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserValidator $userValidator
    ) {}

    /**
     * Display list of users
     */
    public function index(Request $request): Response
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');
        $role = $request->get('role');
        $status = $request->get('status');

        $filters = [
            'search' => $search,
            'role' => $role,
            'status' => $status
        ];

        $users = $this->userService->getUsersPaginated($page, $perPage, $filters);

        return $this->render('admin/users/index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->userService->getAvailableRoles(),
            'statuses' => ['active', 'inactive', 'suspended']
        ]);
    }

    /**
     * Show user creation form
     */
    public function create(Request $request): Response
    {
        return $this->render('admin/users/create', [
            'roles' => $this->userService->getAvailableRoles()
        ]);
    }

    /**
     * Store a new user
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        
        $errors = $this->userValidator->validateCreate($data);
        if (!empty($errors)) {
            return $this->render('admin/users/create', [
                'errors' => $errors,
                'old' => $data,
                'roles' => $this->userService->getAvailableRoles()
            ]);
        }

        try {
            $user = $this->userService->createUser($data);
            
            return $this->redirect('/admin/users/' . $user->getId())
                ->with('success', 'User created successfully');
        } catch (\Exception $e) {
            return $this->render('admin/users/create', [
                'errors' => ['general' => $e->getMessage()],
                'old' => $data,
                'roles' => $this->userService->getAvailableRoles()
            ]);
        }
    }

    /**
     * Show user details
     */
    public function show(Request $request, int $id): Response
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            return $this->notFound();
        }

        $activity = $this->userService->getUserActivity($id, 50);
        $permissions = $this->userService->getUserPermissions($id);

        return $this->render('admin/users/show', [
            'user' => $user,
            'activity' => $activity,
            'permissions' => $permissions
        ]);
    }

    /**
     * Show user edit form
     */
    public function edit(Request $request, int $id): Response
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            return $this->notFound();
        }

        return $this->render('admin/users/edit', [
            'user' => $user,
            'roles' => $this->userService->getAvailableRoles()
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, int $id): Response
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            return $this->notFound();
        }

        $data = $request->all();
        
        $errors = $this->userValidator->validateUpdate($data, $id);
        if (!empty($errors)) {
            return $this->render('admin/users/edit', [
                'user' => $user,
                'errors' => $errors,
                'old' => $data,
                'roles' => $this->userService->getAvailableRoles()
            ]);
        }

        try {
            $this->userService->updateUser($id, $data);
            
            return $this->redirect('/admin/users/' . $id)
                ->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            return $this->render('admin/users/edit', [
                'user' => $user,
                'errors' => ['general' => $e->getMessage()],
                'old' => $data,
                'roles' => $this->userService->getAvailableRoles()
            ]);
        }
    }

    /**
     * Delete user
     */
    public function destroy(Request $request, int $id): Response
    {
        if ($id === $request->user()->getId()) {
            return $this->json(['error' => 'Cannot delete your own account'], 400);
        }

        try {
            $this->userService->deleteUser($id);
            
            if ($request->isAjax()) {
                return $this->json(['success' => true]);
            }
            
            return $this->redirect('/admin/users')
                ->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            if ($request->isAjax()) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
            
            return $this->redirect('/admin/users')
                ->with('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Suspend user account
     */
    public function suspend(Request $request, int $id): Response
    {
        if ($id === $request->user()->getId()) {
            return $this->json(['error' => 'Cannot suspend your own account'], 400);
        }

        try {
            $reason = $request->get('reason');
            $duration = $request->get('duration'); // in days
            
            $this->userService->suspendUser($id, $reason, $duration);
            
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Reactivate suspended user
     */
    public function reactivate(Request $request, int $id): Response
    {
        try {
            $this->userService->reactivateUser($id);
            
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update user permissions
     */
    public function updatePermissions(Request $request, int $id): Response
    {
        $permissions = $request->get('permissions', []);
        
        try {
            $this->userService->updateUserPermissions($id, $permissions);
            
            return $this->json(['success' => true]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, int $id): Response
    {
        try {
            $temporaryPassword = $this->userService->resetUserPassword($id);
            
            return $this->json([
                'success' => true,
                'temporary_password' => $temporaryPassword
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Export users data
     */
    public function export(Request $request): Response
    {
        $format = $request->get('format', 'csv');
        $filters = [
            'search' => $request->get('search'),
            'role' => $request->get('role'),
            'status' => $request->get('status')
        ];

        $data = $this->userService->exportUsers($filters, $format);

        return $this->download($data['content'], $data['filename'], $data['mime_type']);
    }

    /**
     * Bulk actions on users
     */
    public function bulkAction(Request $request): Response
    {
        $action = $request->get('action');
        $userIds = $request->get('user_ids', []);

        if (empty($userIds)) {
            return $this->json(['error' => 'No users selected'], 400);
        }

        try {
            switch ($action) {
                case 'activate':
                    $this->userService->bulkActivateUsers($userIds);
                    $message = 'Users activated successfully';
                    break;
                    
                case 'deactivate':
                    $this->userService->bulkDeactivateUsers($userIds);
                    $message = 'Users deactivated successfully';
                    break;
                    
                case 'delete':
                    $this->userService->bulkDeleteUsers($userIds);
                    $message = 'Users deleted successfully';
                    break;
                    
                case 'change_role':
                    $role = $request->get('role');
                    $this->userService->bulkChangeRole($userIds, $role);
                    $message = 'User roles updated successfully';
                    break;
                    
                default:
                    return $this->json(['error' => 'Invalid action'], 400);
            }

            return $this->json(['success' => true, 'message' => $message]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Show user activity log
     */
    public function activity(Request $request, int $id): Response
    {
        $user = $this->userService->getUserById($id);
        
        if (!$user) {
            return $this->notFound();
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        
        $activity = $this->userService->getUserActivityPaginated($id, $page, $perPage);

        return $this->render('admin/users/activity', [
            'user' => $user,
            'activity' => $activity
        ]);
    }

    /**
     * Impersonate user (for debugging)
     */
    public function impersonate(Request $request, int $id): Response
    {
        if (!$request->user()->hasPermission('admin.impersonate')) {
            return $this->forbidden();
        }

        try {
            $this->userService->impersonateUser($id, $request->user()->getId());
            
            return $this->redirect('/')
                ->with('info', 'You are now impersonating another user');
        } catch (\Exception $e) {
            return $this->redirect('/admin/users/' . $id)
                ->with('error', 'Failed to impersonate user: ' . $e->getMessage());
        }
    }

    /**
     * Stop impersonation
     */
    public function stopImpersonation(Request $request): Response
    {
        $this->userService->stopImpersonation();
        
        return $this->redirect('/admin')
            ->with('info', 'Impersonation ended');
    }
}