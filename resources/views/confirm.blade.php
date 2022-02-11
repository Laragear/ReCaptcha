<!doctype html>
<html lang="{{ config('app.lang') }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ trans('recaptcha::confirmation.title') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<div class="bg-light vh-100">
    <div class="container py-3 h-100">
        <div class="row align-items-center justify-content-center h-100" style="min-height: 400px">
            <div class="col-md-6 col-lg-5 col-xl-4 text-center">
                <div class="card shadow-sm">
                    <form method="post" class="card-body">
                        @csrf
                        <h3 class="card-title">{{ trans('recaptcha::confirmation.title') }}</h3>
                        <p>{{ trans('recaptcha::confirmation.message') }}</p>

                        @error(\Laragear\ReCaptcha\ReCaptcha::INPUT)
                            <p class="text-danger">{{ $message }}</p>
                        @enderror

                        <div class="g-recaptcha mb-3" data-sitekey="{{ recaptcha('checkbox') }}"></div>

                        <button type="submit" class="w-100 btn btn-lg btn-primary">
                            {{ trans('recaptcha::confirmation.submit') }}
                        </button>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
