<?php

namespace Lhm;


class Command {

    public function run(callable $callable = null) {
        try {
            $this->validate();

            if (is_callable($callable)) {
                $this->before();

                $callable($this);

                $this->after();
            } else {
                $this->execute();
            }
        } catch(\Exception $e) {
            // TODO log
            $this->revert();
            throw $e;
        }
    }

    protected function validate() {
    }

    /**
     * Called when the callable or execute() failed.
     */
    protected function revert() {
    }

    protected function execute() {
        throw new \Exception("Not implemented");
    }

    /**
     * Called before the callable is executed.
     */
    protected function before() {
    }

    /**
     * Called after the callable is executed.
     */
    protected function after() {
    }

}
