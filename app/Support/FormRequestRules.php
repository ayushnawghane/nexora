<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Validator;
use ReflectionMethod;

/**
 * Validates input with a FormRequest's own rules, input clean-up, messages and after-hooks, but
 * without its authorize() check. God Mode uses this so a correction meets exactly the rules the
 * normal screen applies, even though the normal screen would refuse the edit (e.g. a non-draft).
 */
class FormRequestRules
{
    /**
     * @param  class-string<FormRequest>  $requestClass
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $routeParameters  what the request reads with $this->route(...)
     * @return array<string, mixed> the validated data
     */
    public static function validate(string $requestClass, array $input, array $routeParameters, User $user): array
    {
        $request = $requestClass::create('/god-mode', 'POST', $input);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $user);

        $route = (new Route('POST', 'god-mode', []))->bind($request);
        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }
        $request->setRouteResolver(fn () => $route);

        // getValidatorInstance() runs prepareForValidation() and builds the validator with the
        // request's rules, messages, attribute names and after-hooks; it is protected on FormRequest.
        /** @var Validator $validator */
        $validator = (new ReflectionMethod($request, 'getValidatorInstance'))->invoke($request);
        $validator->validate();

        return $request->validated();
    }
}
