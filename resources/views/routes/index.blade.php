<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <title>Lista de Rotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
    <div class="container mt-4">
        <h1>Rotas para teste</h1>
        <table class="table table-dark table-striped">
            <thead class="thead-light">
                <tr>
                    <th>Título</th>
                    <th>Descrição</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($routes as $route)
                    <tr>
                        <td>{{ $route['title'] }}</td>
                        <td>{{ $route['description'] }}</td>
                        <td><a href="{{ url($route['uri']) }}" class="btn btn-outline-info">Visitar</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
