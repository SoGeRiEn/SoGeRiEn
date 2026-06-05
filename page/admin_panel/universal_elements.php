<?php
declare(strict_types=1);

$title = 'Sogerien Elements';
?>
<main class="container py-4">
    <section class="mb-4">
        <h1 class="h3 mb-2"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-muted mb-0">Universal page with core UI elements for new Sogerien projects.</p>
    </section>

    <section class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="p-3 border rounded bg-white h-100">
                <h2 class="h6">Buttons</h2>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="button">Primary</button>
                    <button class="btn btn-outline-primary" type="button">Secondary</button>
                    <button class="btn btn-success" type="button">Success</button>
                    <button class="btn btn-danger" type="button">Danger</button>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="p-3 border rounded bg-white h-100">
                <h2 class="h6">Badges</h2>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-primary">active</span>
                    <span class="badge text-bg-warning">pending</span>
                    <span class="badge text-bg-secondary">archive</span>
                    <span class="badge text-bg-danger">delete</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="p-3 border rounded bg-white h-100">
                <h2 class="h6">Alerts</h2>
                <div class="alert alert-info py-2 mb-2">Info alert</div>
                <div class="alert alert-success py-2 mb-0">Success alert</div>
            </div>
        </div>
    </section>

    <section class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="p-3 border rounded bg-white">
                <h2 class="h6">Form</h2>
                <form class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="el-login">Login</label>
                        <input class="form-control" id="el-login" name="login" type="text" value="admin">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="el-role">Role</label>
                        <select class="form-select" id="el-role" name="role">
                            <option>admin</option>
                            <option>user</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="el-note">Note</label>
                        <textarea class="form-control" id="el-note" name="note" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" id="el-active" type="checkbox" checked>
                            <label class="form-check-label" for="el-active">Active</label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="p-3 border rounded bg-white">
                <h2 class="h6">Table</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Default Admin</td>
                                <td><span class="badge text-bg-success">active</span></td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button">Edit</button></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Example User</td>
                                <td><span class="badge text-bg-secondary">archive</span></td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-primary" type="button">Edit</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

