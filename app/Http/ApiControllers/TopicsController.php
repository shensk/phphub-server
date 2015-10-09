<?php

namespace PHPHub\Http\ApiControllers;

use Auth;
use Dingo\Api\Exception\StoreResourceFailedException;
use Gate;
use PHPHub\Repositories\TopicRepositoryInterface;
use PHPHub\Transformers\TopicTransformer;
use Illuminate\Http\Request;
use Prettus\Validator\Exceptions\ValidatorException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TopicsController extends Controller
{
    /**
     * @var TopicRepositoryInterface
     */
    private $topics;

    /**
     * TopicController constructor.
     *
     * @param TopicRepositoryInterface $repository
     */
    public function __construct(TopicRepositoryInterface $repository)
    {
        $this->topics = $repository;
    }

    /**
     * 默认帖子列表.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->commonIndex();
    }

    /**
     * 获取指定用户发布的帖子.
     *
     * @param $user_id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function indexByUserId($user_id)
    {
        $this->topics->byUserId($user_id);

        return $this->commonIndex();
    }

    /**
     * 获取指定节点下的帖子.
     *
     * @param $node_id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function indexByNodeId($node_id)
    {
        $this->topics->byNodeId($node_id);

        return $this->commonIndex();
    }

    /**
     * 用户收藏的帖子列表.
     *
     * @param $user_id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function indexByUserFavorite($user_id)
    {
        $this->registerListApiIncludes();

        $data = $this->topics
            ->favoriteTopicsWithPaginator($user_id,
                ['id', 'title', 'is_excellent', 'reply_count', 'updated_at', 'created_at']);

        return $this->response()->paginator($data, new TopicTransformer());
    }
    /**
     * 用户收藏的帖子列表.
     *
     * @param $user_id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function indexByUserAttention($user_id)
    {
        $this->registerListApiIncludes();

        $data = $this->topics
            ->attentionTopicsWithPaginator($user_id,
                ['id', 'title', 'is_excellent', 'reply_count', 'updated_at', 'created_at']);

        return $this->response()->paginator($data, new TopicTransformer());
    }

    /**
     * 发布新帖子.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $topic = $this->topics->store($request->all());

            return $this->response()->item($topic, new TopicTransformer());
        } catch (ValidatorException $e) {
            throw new StoreResourceFailedException('Could not create new topic.', $e->getMessageBag()->all());
        }
    }

    /**
     * 获取指定帖子的详细.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->topics->addAvailableInclude('user', ['name', 'avatar']);

        $topic = $this->topics->skipPresenter()->autoWith()->find($id);

        if (Auth::check()) {
            $topic->favorite  = $this->topics->userFavorite($topic->id, Auth::id());
            $topic->attention = $this->topics->userAttention($topic->id, Auth::id());
        }

        return $this->response()->item($topic, new TopicTransformer());
    }

    /**
     * 更新帖子.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * 删除帖子.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $topic = $this->topics->find($id);

        if (Gate::denies('delete', $topic)) {
            throw new AccessDeniedHttpException();
        }

        $this->topics->delete($id);
    }

    /**
     * 支持帖子.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function voteUp($id)
    {
        $topic = $this->topics->find($id);

        return response([
            'vote-up'    => $this->topics->voteUp($topic),
            'vote_count' => $topic->vote_count,
        ]);
    }

    /**
     * 反对帖子.
     *
     * @param $id
     *
     * @return \Illuminate\Http\Response
     */
    public function voteDown($id)
    {
        $topic = $this->topics->find($id);

        return response([
            'vote-down'  => $this->topics->voteDown($topic),
            'vote_count' => $topic->vote_count,
        ]);
    }

    /**
     * 所有帖子列表接口的通用部分.
     *
     * @return \Dingo\Api\Http\Response
     */
    protected function commonIndex()
    {
        $this->registerListApiIncludes();

        $data = $this->topics
            ->autoWith()
            ->skipPresenter()
            ->autoWithRootColumns([
                'id', 'title', 'is_excellent', 'reply_count', 'updated_at', 'created_at', 'vote_count',
            ])
            ->paginate(per_page());

        return $this->response()->paginator($data, new TopicTransformer());
    }

    /**
     * 用于客户端的帖子详细 Web View.
     *
     * @param $id
     *
     * @return \Illuminate\View\View
     */
    public function showWebView($id)
    {
        $topic = $this->topics->find($id, ['title', 'body', 'created_at', 'vote_count']);

        return view('api_web_views.topic', compact('topic'));
    }

    /**
     * 注册帖子列表接口通用的引入项.
     */
    protected function registerListApiIncludes()
    {
        $this->topics->addAvailableInclude('user', ['name', 'avatar']);
        $this->topics->addAvailableInclude('last_reply_user', ['name']);
        $this->topics->addAvailableInclude('node', ['name']);
    }
}
