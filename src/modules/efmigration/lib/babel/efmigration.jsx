(function($, React, ReactDOM) {
    const STEP_STATUS_IDLE = 'idle';
    const STEP_STATUS_RUNNING = 'running';
    const STEP_STATUS_SUCCESS = 'success';
    const STEP_STATUS_ERROR = 'error';

    class StepRow extends React.Component {
        constructor(props) {
            super(props);
        }

        render() {
            const id = 'pp-step-' + this.props.name;
            const className = 'pp-status-' + this.props.status;

            return (
                <li id={id} className={className}>
                    {this.props.label}
                </li>
            )
        }
    }

    class ErrorRow extends React.Component {
        render() {
            return (
                <li>{this.props.msg}</li>
            )
        }
    }

    class StepList extends React.Component {
        constructor(props) {
            super(props);
        }

        render() {
            const finished   = this.props.finished;
            const steps      = this.props.steps;
            const errors     = this.props.errors;
            const hasErrors  = errors.length > 0;

            var stepRows = steps.map((step) =>
                <StepRow
                    key={step.key}
                    name={step.key}
                    status={step.status}
                    label={step.label} />
            );

            var errorRows = errors.map((error) =>
                <ErrorRow key={error.key} msg={error.msg} />
            );

            return (
                <div>
                    <div className="pp-progressbar-container">
                        <ol className="pp-progressbar">
                            {stepRows}
                        </ol>
                    </div>

                    {!finished
                    &&
                        <p>{objectL10n.header_msg}</p>
                    ||
                        <p>{objectL10n.success_msg}</p>
                    }

                    {hasErrors
                    &&
                        <div className="pp-errors">
                            <h2>{objectL10n.error}</h2>
                            <ul>
                                {errorRows}
                            </ul>
                            <p>{objectL10n.error_msg_intro} <a href="mailto:help@pressshack.com">{objectL10n.error_msg_contact}</a></p>
                        </div>
                    }
                </div>
            );
        }
    }

    class StepListContainer extends React.Component {
        constructor() {
            super();

            this.state = {
                steps: [
                    {
                        key: 'options',
                        label: objectL10n.options,
                        status: STEP_STATUS_IDLE,
                        error: null
                    },
                    {
                        key: 'taxonomy',
                        label: objectL10n.taxonomy,
                        status: STEP_STATUS_IDLE,
                        error: null
                    },
                    {
                        key: 'user-meta',
                        label: objectL10n.user_meta,
                        status: STEP_STATUS_IDLE,
                        error: null
                    },
                ],
                currentStepIndex: -1,
                finished: false,
                errors: [],
            };
        }

        componentDidMount() {
            setTimeout(
                () => {
                    this.executeNextStep();
                },
                700
            );
        }

        executeNextStep() {
            // Go to the next step index.
            this.setState({currentStepIndex: this.state.currentStepIndex + 1});

            // Check if we finished the step list to finish the process.
            if (this.state.currentStepIndex >= this.state.steps.length) {
                this.setState({finished: true});

                return;
            }

            // We have a step. Lets execute it.
            var currentStep = this.state.steps[this.state.currentStepIndex];

            // Set status of step in progress
            currentStep.status = STEP_STATUS_RUNNING;
            this.updateStep(currentStep);

            // Call the method to migrate and wait for the response
            const data = {
                'action': 'pp_migrate_ef_data',
                'step': currentStep.key
            };
            $.post(ajaxurl, data, (response) => {
                var step = this.state.steps[this.state.currentStepIndex];

                if (typeof response.error === 'string') {
                    // Error
                    step.status = STEP_STATUS_ERROR;
                    this.appendError('[' + step.key + '] ' + response.error);
                } else {
                    // Success
                    step.status = STEP_STATUS_SUCCESS;
                }

                this.updateStep(step);
                this.executeNextStep();
            }, 'json');
        }

        updateStep(newStep) {
            var index  = this.state.currentStepIndex;

            var newSteps = this.state.steps.map((step) => {
                return step.key === newStep.key ? newStep : step;
            });

            this.setState({steps: newSteps});
        }

        appendError(msg) {
            var errors = this.state.errors;
            errors.push({key: errors.length, msg: msg});

            this.setState({errors: errors});
        }

        render() {
            return <StepList steps={this.state.steps} finished={this.state.finished} errors={this.state.errors} />;
        }
    }

    ReactDOM.render(<StepListContainer />, document.getElementById('pp-content'));
})(jQuery, React, ReactDOM);