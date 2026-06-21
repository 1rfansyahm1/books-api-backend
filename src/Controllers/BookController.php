<?php
namespace App\Controllers;
use App\Repositories\BookRepository;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BookController
{
    public function __construct(private BookRepository $books) {}

    /** GET /api/books — supports ?q= and ?limit= */
    public function index(Request $r, Response $s): Response {
    $p = $r->getQueryParams();
    $rows = $this->books->all((string)($p['q'] ?? ''), (int)($p['limit'] ?? 0));
    return $this->json($s, ['count'=>count($rows), 'data'=>$rows]);
    }

    /** GET /api/books/{id} */
   public function show(Request $r, Response $s, array $a): Response {
    $book = $this->books->find((int)$a['id']);
    return $book ? $this->json($s, $book)
    : $this->json($s, ['error'=>'not found'], 404);
    }

    /** POST /api/books */
    public function create(Request $r, Response $s): Response {
    $body = (array)$r->getParsedBody();
    $errors = (new Validator())
    ->required('title', 'author', 'year')
    ->field('title', Validator::nonEmptyString(200), 'title must be 1-200 chars')
    ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
    ->field('year', Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
    ->field('genre', Validator::nonEmptyString(80), 'genre must be ≤ 80 chars')
    ->validate($body);
    if ($errors) return $this->json($s, ['errors'=>$errors], 400);
    $auth = (array)$r->getAttribute('auth', []);
    $id = $this->books->create($body, (int)($auth['sub'] ?? 0));
    return $this->json($s, ['message'=>'Book created', 'data'=>$this->books->find($id)],
    201)
    ->withHeader('Location', '/api/books/' . $id);
    }


    /** PUT /api/books/{id} — full or partial update */
    /** PUT /api/books/{id} — full or partial update */
    public function update(Request $req, Response $res, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        
        // 1. Verify the book exists using the repository
        $currentBook = $this->books->find($id);
        if (!$currentBook) {
            return $this->json($res, ['error' => "Book {$id} not found"], 404);
        }

        // 2. Authorization check (Moved from Repository)
        $auth = (array)$req->getAttribute('auth', []);
        $isOwner = (int)$currentBook['created_by'] === (int)($auth['sub'] ?? 0);
        $isAdmin = ($auth['role'] ?? 'member') === 'admin';
        
        if (!$isOwner && !$isAdmin) {
            return $this->json($res, ['error' => 'Forbidden'], 403);
        }

        // 3. Validate incoming data
        $body = (array)($req->getParsedBody() ?? []);
        $errors = (new Validator())
            ->field('title', Validator::nonEmptyString(200), 'title must be 1-200 chars')
            ->field('author', Validator::nonEmptyString(150), 'author must be 1-150 chars')
            ->field('year', Validator::intRange(1000, (int)date('Y')), 'year must be 1000..now')
            ->field('genre', Validator::nonEmptyString(80), 'genre must be ≤ 80 chars')
            ->validate($body, true); // partial update

        if (!empty($errors)) {
            return $this->json($res, ['errors' => $errors], 400);
        }

        // 4. Pass the validated data to the repository to handle the actual update
        $this->books->update($id, $body);

        // Fetch the freshly updated record to return to the user
        $updatedBook = $this->books->find($id);

        return $this->json($res, ['message' => 'Book updated', 'data' => $updatedBook]);
    }

    /** DELETE /api/books/{id} */
    public function delete(Request $req, Response $res, array $args): Response
    {
        $auth = (array)$req->getAttribute('auth', []);
        if (($auth['role'] ?? 'member') !== 'admin') {
        return $this->json($res, ['error' => 'Admins only'], 403);
        }
        
        $id = (int)($args['id'] ?? 0);
        
        // 1. Verify the book exists
        $bookToDelete = $this->books->find($id);
        if (!$bookToDelete) {
            return $this->json($res, ['error' => "Book {$id} not found"], 404);
        }

        // 2. Delete using the repository
        $this->books->delete($id);

        return $this->json($res, ['message' => 'Book deleted', 'data' => $bookToDelete]);
    }

    private function validate(array $b, bool $requireAll): array {
        $errors = [];

        $rules = [
        'title' => fn($v) => is_string($v) && trim($v) !== '',
        'author' => fn($v) => is_string($v) && trim($v) !== '',
        'year' => fn($v) => is_numeric($v) && (int)$v >= 1000 && (int)$v <= (int)date('Y'),
        ];
        foreach ($rules as $f => $check) {
        if ($requireAll && !array_key_exists($f, $b)) { $errors[$f]="$f is required";
        continue; 
        }
        if (array_key_exists($f, $b) && !$check($b[$f])) {$errors[$f] = "$f is
        invalid";
        }
        }
        return $errors;
    }

    private function json(Response $r, $data, int $code=200): Response {
    $r->getBody()->write(json_encode(
        $data, 
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ));
    return $r->withHeader('Content-Type','application/json; charset=utf-8')->withStatus($code);
    }
}
