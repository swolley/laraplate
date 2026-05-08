# Bugfix Requirements Document

## Introduction

Il trait `HasValidations` chiama internamente `$this->getAttributesForValidation()` (riga 96 e 142 di `HasValidations.php`), ma questo metodo è definito solo in `Modules\Core\Overrides\Model` — la classe base custom del progetto.

I modelli `User` e `Role` usano il trait `HasValidations` direttamente, ma estendono rispettivamente `Illuminate\Foundation\Auth\User` e `Spatie\Permission\Models\Role`, che non passano per `Modules\Core\Overrides\Model`. Di conseguenza, quando `validateWithRules()` viene invocato su questi modelli, PHP non trova il metodo `getAttributesForValidation()` e lancia una `BadMethodCallException`.

Questo causa il fallimento di tutti i test che coinvolgono `User` e `Role` (20 test in totale tra `UpdateUserPasswordTest`, `RoleTest` e `ChatServiceFullTest`).

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN un modello usa il trait `HasValidations` ma non estende `Modules\Core\Overrides\Model` (es. `User` che estende `BaseUser`, `Role` che estende `BaseRole`) THEN il sistema lancia `BadMethodCallException: Call to undefined method ::getAttributesForValidation()` durante la validazione

1.2 WHEN `validateWithRules()` viene chiamato su `User` o `Role` (ad esempio durante la creazione o l'aggiornamento) THEN il sistema fallisce con un'eccezione non gestita perché `getAttributesForValidation()` non è presente nella gerarchia di ereditarietà del modello

1.3 WHEN i test che usano factory di `User` o `Role` vengono eseguiti THEN i test falliscono con `BadMethodCallException` anche se la logica di business testata è corretta

### Expected Behavior (Correct)

2.1 WHEN un modello usa il trait `HasValidations` ma non estende `Modules\Core\Overrides\Model` THEN il sistema SHALL fornire un'implementazione di default di `getAttributesForValidation()` direttamente nel trait, restituendo `$this->getAttributes()`

2.2 WHEN `validateWithRules()` viene chiamato su `User` o `Role` THEN il sistema SHALL eseguire la validazione correttamente senza eccezioni, usando gli attributi del modello

2.3 WHEN i test che usano factory di `User` o `Role` vengono eseguiti THEN i test SHALL passare senza `BadMethodCallException` legata a `getAttributesForValidation()`

### Unchanged Behavior (Regression Prevention)

3.1 WHEN un modello estende `Modules\Core\Overrides\Model` (che definisce già `getAttributesForValidation()`) THEN il sistema SHALL CONTINUE TO usare l'implementazione del metodo definita nella classe concreta, senza che il trait sovrascriva il comportamento

3.2 WHEN un modello usa il trait `HasPlace` (che fa override di `getAttributesForValidation()` chiamando `parent::getAttributesForValidation()`) THEN il sistema SHALL CONTINUE TO eseguire la catena di chiamate correttamente, includendo i campi del place nella validazione

3.3 WHEN la validazione viene eseguita su qualsiasi modello che estende `Modules\Core\Overrides\Model` THEN il sistema SHALL CONTINUE TO validare gli attributi del modello con le regole definite in `getRules()`

3.4 WHEN `setSkipValidation(true)` è impostato su un modello THEN il sistema SHALL CONTINUE TO saltare la validazione senza chiamare `getAttributesForValidation()`

---

## Bug Condition (Pseudocode)

**Bug Condition Function** — identifica i modelli che scatenano il bug:

```pascal
FUNCTION isBugCondition(Model)
  INPUT: Model — istanza di un Eloquent model che usa HasValidations
  OUTPUT: boolean

  // Il bug si verifica quando il modello usa HasValidations
  // ma NON eredita getAttributesForValidation() dalla gerarchia
  RETURN uses_trait(Model, HasValidations)
     AND NOT method_exists_in_hierarchy(Model, 'getAttributesForValidation')
END FUNCTION
```

Esempi concreti: `User` (estende `BaseUser`), `Role` (estende `BaseRole`).

**Property: Fix Checking**

```pascal
FOR ALL Model WHERE isBugCondition(Model) DO
  result ← Model.validateWithRules(operation)
  ASSERT no_exception(result, BadMethodCallException)
  ASSERT validation_executed_with_model_attributes(result)
END FOR
```

**Property: Preservation Checking**

```pascal
FOR ALL Model WHERE NOT isBugCondition(Model) DO
  // Modelli che estendono Modules\Core\Overrides\Model
  ASSERT F(Model.validateWithRules) = F'(Model.validateWithRules)
END FOR
```
