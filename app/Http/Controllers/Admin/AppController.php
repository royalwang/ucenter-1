<?php namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Requests\AppRequest;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Model\App;
use Redirect, Input, Auth;
use Illuminate\Support\Facades\DB;
use App\Services\Helper;
use Queue;
use App\Model\Role;
use App\Model\Permission;
use App\Jobs\UserLog;
use App\Services\Api;

class AppController extends Controller {

	public function index()
	{
		return view('admin.app.index');
	}

	public function lists(Request $request)
	{
		$fields = array('id', 'name', 'title', 'user_id', 'created_at', 'updated_at');
        $searchFields = array('name', 'title');

        $data = App::where('user_id', Auth::id())
            ->whereDataTables($request, $searchFields)
            ->orderByDataTables($request)
			->skip($request->start)
			->take($request->length)
			->get($fields)
            ->toArray();
        $draw = (int)$request->draw;
		$recordsFiltered = count($data);
		$recordsTotal = App::where('user_id', Auth::id())->count();

        return Api::dataTablesReturn(compact('draw', 'recordsFiltered', 'recordsTotal', 'data'));
	}

	public function create()
	{
		return view('admin.app.create');
	}

	public function store(AppRequest $request)
	{
		$app = App::create(array('name' => $request->name,
			'title' => $request->title,
			'description' => $request->description,
			'home_url' => $request->home_url,
			'login_url' => $request->login_url,
			'secret' => $request->secret,
			'user_id' => Auth::id()
		));

        // 接入oauth_clients
        $oauth_client = DB::table('oauth_clients')->insert(array(
            'id' => $request->name,
            'secret' => $request->secret,
            'name' => $request->title,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ));

        // 默认开发者角色
        $role = Role::create(array(
            'app_id' => $app->id,
            'name' => 'developer',
			'title' => '开发者',
			'description' => '开发者',
		));

        $user_role = DB::table('user_role')->insert(array(
            'user_id' => Auth::id(),
            'app_id' => $app->id,
            'role_id' => $role->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ));

		// $log = Queue::push(new UserLog(1, Auth::id(), 'A', '新增应用', 'name : ' . $request->name . '; title : ' . $request->title, '', $ip, $ips));
		if ($app && $oauth_client && $role && $user_role) {
			session()->flash('success_message', '应用添加成功');
			return Redirect::to('/admin/app');
		} else {
			return Redirect::back()->withInput()->withErrors('保存失败！');
		}
	}

	public function show($id)
	{
	}

	public function edit(AppRequest $request, $id)
	{
		return view('admin.app.edit')->withApp(App::find($id));
	}

	public function update(AppRequest $request, $id)
	{
		$this->validate($request, [
			'name' => 'required|unique:apps,name,'.$id.'',
		]);

        $app = App::where('id', $id)->update(array(
            'name' => $request->name,
			'title' => $request->title,
			'description' => $request->description,
			'home_url' => $request->home_url,
			'login_url' => $request->login_url,
			'secret' => $request->secret,
			'user_id' => Auth::id()
		));

        $oauth_client = DB::table('oauth_clients')->where('id', $id)->update(array(
            'id' => $request->name,
            'secret' => $request->secret,
            'name' => $request->title,
            'updated_at' => date('Y-m-d H:i:s')
        ));

		if ($app && $oauth_client) {
			session()->flash('success_message', '应用修改成功');
			return Redirect::to('/admin/app');
		} else {
			return Redirect::back()->withInput()->withErrors('保存失败！');
		}
	}

	public function destroy($id)
	{
		return false;
	}

	// 删除
	public function delete()
	{
		DB::beginTransaction();
		try {
			$ids = $_POST['ids'];
			// Auth::user()->can('delete-all-app');
			$result = App::whereIn('id', $ids)->delete();

            DB::table('oauth_clients')->whereIn('id', $ids)->delete();
			DB::commit();
			return Api::jsonReturn(1, '删除成功', array('deleted_num' => $result));
		} catch (Exception $e) {
			DB::rollBack();
			throw $e;
			return Api::jsonReturn(0, '删除失败', array('deleted_num' => 0));
		}
	}
}
